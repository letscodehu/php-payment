<?php

namespace App\User;


class SubscriptionInfo
{
    private bool $active;
    private ?string $plan;
    private array $transactions;
    private ?string $id;
    private ?string $status;
    private ?string $expires;

    public function __construct(bool $active, ?string $plan = null, array $transactions = [], ?string $status = null, ?string $id = null, ?string $expires = null)
    {
        $this->active = $active;
        $this->status = $status;
        $this->id = $id;
        $this->plan = $plan;
        $this->expires = $expires;
        $this->transactions = $transactions;
        $this->id = $id;
        $this->status = $status;
        $this->expires = $expires;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function getExpires(): ?string
    {
        return $this->expires;
    }


    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getExpiry(): ?string
    {
        return $this->expires;
    }

    public function cancellable() : bool {
        return $this->active && $this->status != 'Cancelled';
    }
}
