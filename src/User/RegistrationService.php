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

    public function register(string $email): int
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
        return $this->getUserIdByEmail($email);
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
        extract($with);
        ob_start();
        require(__DIR__ . "/../../templates/activate_email.phtml");
        $content = ob_get_clean();
        $email = (new Email())
            ->from('noreply@letscode.hu')
            ->to($email)
            ->subject("Successful registration")
            ->text("Click here to activate your user: $url")
            ->html($content);
        $this->mailer->send($email);
    }

    public function activate(?string $token, ?string $password, ?string $confirmation): bool
    {
        $userId = $this->getUserId($token);
        if ($password === $confirmation && $userId > 0) {
            $this->updatePassword($userId, $password);
            $this->removeToken($token);
            return true;
        }
        return false;
    }

    private function getUserId(string $token): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM password_reset_token WHERE id = :token AND expires > :expires");
        $stmt->execute(["expires" => time(), "token" => $token]);
        return $stmt->fetchColumn(0);
    }

    private function getUserIdByEmail(string $email): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM user WHERE username = :email");
        $stmt->execute(["email" => $email]);
        return $stmt->fetchColumn(0);
    }

    private function updatePassword($userId, string $password): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user SET password = :password WHERE id = :user_id");
        $stmt->execute(["user_id" => $userId, "password" => password_hash($password, PASSWORD_BCRYPT)]);
        return true;
    }

    private function removeToken($token)
    {
        $stmt = $this->pdo->prepare("DELETE FROM password_reset_token WHERE id = :token");
        $stmt->execute(["token" => $token]);
    }
}
