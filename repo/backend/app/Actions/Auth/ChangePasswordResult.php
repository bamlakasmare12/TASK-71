<?php

namespace App\Actions\Auth;

class ChangePasswordResult
{
    private function __construct(
        public readonly bool $success,
        public readonly array $errors = [],
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failed(array $errors): self
    {
        return new self(false, $errors);
    }
}
