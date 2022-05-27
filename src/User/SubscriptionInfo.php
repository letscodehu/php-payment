<?php

namespace App\User;

class SubscriptionInfo
{
    private bool $active;
    private ?string $plan;
    private array $transactions;

    public function __construct(bool $active, ?string $plan, array $transactions)
    {
        $this->active = $active;
        $this->plan = $plan;
        $this->transactions = $transactions;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }
}
