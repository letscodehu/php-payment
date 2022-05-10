<?php

namespace App\User;

use Exception;
use PDO;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RegistrationService
{

    private MailerInterface $mailer;
    private PDO $pdo;

    public function __construct(MailerInterface $mailer, PDO $pdo)
    {
        $this->mailer = $mailer;
        $this->pdo = $pdo;
    }

    public function register(string $email)
    {
        if ($this->notYetRegistered($email)) {
            try {
                $this->pdo->beginTransaction();
                $id = $this->createUser($email);
                $token = $this->associateToken($id);
                $this->sendMail($email, $token);
                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
    }

    private function createUser(string $email): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO user (username) VALUES (:email)");
        $stmt->execute(["email" => $email]);
        return $this->pdo->lastInsertId();
    }
    private function associateToken(int $userId): string
    {
        $token = hash("sha512", uniqid());
        $expires = time() + 3600;
        $stmt = $this->pdo->prepare("INSERT INTO password_reset_token (id, user_id, expires) VALUES (:token, :user_id, :expires )");
        $stmt->execute(["user_id" => $userId, "token" => $token, "expires" => $expires]);
        return $token;
    }

    private function notYetRegistered(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT username FROM user WHERE username = :email");
        $stmt->execute(["email" => $email]);
        return $stmt->fetch() === false;
    }

    private function sendMail(string $email, string $token)
    {
        $url = "http://localhost:8888/user/activate/" . $token;
        $email = (new Email())
            ->from('noreply@letscode.hu')
            ->to($email)
            ->subject("Successful registration")
            ->text("Click here to activate your user: $url")
            ->html("<p>Click <a href='$url'>here</a> to activate your user.</p>");
        $this->mailer->send($email);
    }
}
