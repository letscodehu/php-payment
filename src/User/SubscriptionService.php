<?php

namespace App\User;

use App\Paypal\Client;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Exception\ClientException;
use PDO;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SubscriptionService
{

    private PDO $pdo;
    private MailerInterface $mailer;
    private Client $client;

    public function __construct(PDO $pdo, MailerInterface $mailer, Client $client)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->mailer = $mailer;
    }

    public function activate(int $userId, string $externalSubscriptionId, string $itemNumber)
    {
        if ($this->notYetSubscribed($externalSubscriptionId)) {
            $stmt = $this->pdo->prepare("INSERT INTO subscription (id, user_id, product_id, status) VALUES (:id, :user_id, :product_id, :status)");
            $stmt->execute([
                "id" => $externalSubscriptionId,
                "user_id" => $userId,
                "product_id" => $itemNumber,
                "status" => "Active"
            ]);
        }
    }

    public function addTime(string $externalSubscriptionId, string $txnId): void
    {
        if ($this->notYetSubscribed($externalSubscriptionId)) {
            return;
        }
        if ($this->transactionNotProcessed($txnId)) {
            $this->pdo->beginTransaction();
            try {
                $this->addTimeToSubscription($externalSubscriptionId, $txnId);
                $this->sendSubscriptionExtendedEmail($txnId);
                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }

    public function hasActiveSubscription(string $identity): bool
    {
        $stmt = $this->pdo->prepare("SELECT txn_id FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id WHERE sp.start < :current AND :current < sp.end AND u.username = :email");
        $stmt->execute([
            "current" => (new DateTimeImmutable())->getTimestamp(),
            "email" => $identity
        ]);
        return $stmt->fetch() !== false;
    }

    public function getSubscriptionInfo(string $identity): SubscriptionInfo
    {
        $active = $this->hasActiveSubscription($identity);
        if (!$active) {
            return new SubscriptionInfo(false);
        }
        list($id, $plan, $status) = $this->getActiveSubscription($identity);
        $transactions = $this->getTransactions($identity);
        $expiry = 0;
        array_walk($transactions, function($t) use (&$expiry) {
            $expiry = max($expiry, $t["end"]);
        });
        return new SubscriptionInfo($active, $plan, $transactions, $status, $id, date("Y.m.d. H:i:s", $expiry));
    }

    private function getActiveSubscription(string $identity): array
    {
        $stmt = $this->pdo->prepare("SELECT s.id, p.name, s.status FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id JOIN product AS p ON p.id = s.product_id WHERE sp.start < :current AND :current < sp.end AND u.username = :email");
        $stmt->execute([
            "current" => (new DateTimeImmutable())->getTimestamp(),
            "email" => $identity
        ]);
        return $stmt->fetch(PDO::FETCH_NUM);
    }

    private function getTransactions(string $identity): array
    {
        $stmt = $this->pdo->prepare("SELECT sp.txn_id as id, sp.start as time, sp.end, p.price as amount, p.name as plan FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id JOIN product AS p ON p.id = s.product_id WHERE u.username = :email");
        $stmt->execute([
            "email" => $identity
        ]);
        return array_map(function ($row) {
            $row["time"] = date("Y.m.d. H:i:s", $row["time"]);
            $row["amount"] = $row["amount"] . " Ft";
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function notYetSubscribed(string $externalSubscriptionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM subscription WHERE id = :id");
        $stmt->execute(["id" => $externalSubscriptionId]);
        return $stmt->fetch() === false;
    }

    private function sendSubscriptionExtendedEmail(string $txnId): void
    {
        $url = "http://localhost:8888";
        $stmt = $this->pdo->prepare("SELECT u.username as email, sp.start, sp.end, p.name AS plan FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id JOIN product AS p ON p.id = s.product_id WHERE sp.txn_id = :id");
        $stmt->execute(["id" => $txnId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        extract($row);
        $start = date("Y.m.d H:i:s", $start);
        $end = date("Y.m.d H:i:s", $end);
        ob_start();
        require(__DIR__ . "/../../templates/subscribe_email.phtml");
        $content = ob_get_clean();
        $email = (new Email())
            ->from('noreply@letscode.hu')
            ->to($email)
            ->subject("Subscription extended!")
            ->text("Your subscription is extended until $end. You can cancel your subscription anytime at your profile page.")
            ->html($content);
        $this->mailer->send($email);
    }

    private function addTimeToSubscription(string $externalSubscriptionId, string $txnId): void
    {
        $start = new DateTimeImmutable();
        $end = new DateTimeImmutable("now + 30 days");
        $stmt = $this->pdo->prepare("INSERT INTO subscription_period (subscription_id, txn_id, start, end ) VALUES (:subscription_id, :txn_id, :start, :end)");
        $stmt->execute([
            "subscription_id" => $externalSubscriptionId,
            "txn_id" => $txnId,
            "start" => $start->getTimestamp(),
            "end" => $end->getTimestamp()
        ]);
    }

    private function transactionNotProcessed(string $txnId): bool
    {
        $stmt = $this->pdo->prepare("SELECT txn_id FROM subscription_period WHERE txn_id = :id");
        $stmt->execute(["id" => $txnId]);
        return $stmt->fetch() === false;
    }

    public function cancelSubscription(string $identity): void
    {
        $subscriptionInfo = $this->getSubscriptionInfo($identity);
        if ($subscriptionInfo->isActive()) {
            try {
                $this->pdo->beginTransaction();
                $stmt = $this->pdo->prepare("UPDATE subscription SET status = 'Cancelled' WHERE id = :id");
                $stmt->execute(["id" => $subscriptionInfo->getId()]);
                $this->client->cancelSubscription($subscriptionInfo->getId());
                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }
}
