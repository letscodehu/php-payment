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

    public function activate(string $userId, string $externalSubscriptionId, string $itemNumber): void
    {
        if ($this->notYetSubscribed($externalSubscriptionId)) {
            $stmt = $this->pdo->prepare("INSERT INTO subscription (id, user_id, product_id, status ) VALUES (:id, 
            :user_id, :product_id, :status)");
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
            $this->addTimeToSubsciption($externalSubscriptionId, $txnId);
            $this->sendSubscriptionExtendedMail($externalSubscriptionId);
        }
    }

    public function hasActiveSubscription(string $identity) : bool {
        $stmt = $this->pdo->prepare("SELECT txn_id FROM subscription_period AS sp JOIN subscription AS s ON s.id = sp.subscription_id JOIN user AS u ON u.id = s.user_id WHERE sp.start < :current AND :current < sp.end AND u.username = :email");
        $stmt->execute(["current" => (new DateTimeImmutable())->getTimestamp(), "email" => $identity]);
        return $stmt->fetch() !== false;
    }

    private function notYetSubscribed(string $externalSubscriptionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM subscription WHERE id = :id");
        $stmt->execute(["id" => $externalSubscriptionId]);
        return $stmt->fetch() === false;
    }

    private function transactionNotProcessed(string $txnId): bool
    {
        $stmt = $this->pdo->prepare("SELECT txn_id FROM subscription_period WHERE txn_id = :id");
        $stmt->execute(["id" => $txnId]);
        return $stmt->fetch() === false;
    }

    private function addTimeToSubsciption(string $externalSubscriptionId, string $txnId): void
    {
        $start = new DateTimeImmutable();
        $end = new DateTimeImmutable("now + 30 days");
        $stmt = $this->pdo->prepare("INSERT INTO subscription_period (subscription_id, txn_id, start, end) VALUES (:subscription_id, :txn_id, :start, :end)");
        $stmt->execute([
            "subscription_id" => $externalSubscriptionId,
            "txn_id" => $txnId,
            "start" => $start->getTimestamp(),
            "end" => $end->getTimestamp()
        ]);
    }

    private function sendSubscriptionExtendedMail(string $externalSubscriptionId): void
    {
    }
}
