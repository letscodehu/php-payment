<?php

namespace App\Paypal;

class Token {

    private string $token;
    private int $expires;

    public function __construct(string $token, int $expires)
    {
        $this->token = $token;
        $this->expires = $expires;
    }

    public function expired() : bool {
        return time() >= $this->expires;
    }

    public function __toString()
    {
        return $this->token;
    }

}

