<?php

namespace App\User;

use DateTimeImmutable;
use PDO;
use Symfony\Component\Mailer\MailerInterface;

class SubscriptionService
{

    private PDO $pdo;
    private MailerInterface $mailer;

    public function __construct(PDO $pdo, MailerInterface $mailer)
    {
        $this->pdo = $pdo;
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
            $this->addTimeToSubscription($externalSubscriptionId, $txnId);
            $this->sendSubscriptionExtendedEmail($externalSubscriptionId);
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
            return new SubscriptionInfo(false, null, []);
        }
        $plan = $this->getActivePlan($identity);
        $transactions = $this->getTransactions($identity);
        return new SubscriptionInfo($active, $plan, $transactions);
    }

    private function getActivePlan(string $identity): string
    {
        $stmt = $this->pdo->prepare("SELECT p.name FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id JOIN product AS p ON p.id = s.product_id WHERE sp.start < :current AND :current < sp.end AND u.username = :email");
        $stmt->execute([
            "current" => (new DateTimeImmutable())->getTimestamp(),
            "email" => $identity
        ]);
        return $stmt->fetchColumn(0);
    }

    private function getTransactions(string $identity): array
    {
        $stmt = $this->pdo->prepare("SELECT sp.txn_id as id, sp.start as time, p.price as amount, p.name as plan FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id JOIN product AS p ON p.id = s.product_id WHERE u.username = :email");
        $stmt->execute([
            "email" => $identity
        ]);
        return array_map(function ($row) {
            $row["time"] = date("yy.m.d. H:i:s", $row["time"]);
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


    private function sendSubscriptionExtendedEmail(string $externalSubscriptionId): void
    {
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
}
