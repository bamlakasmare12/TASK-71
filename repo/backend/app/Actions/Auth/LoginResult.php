<?php

namespace App\Actions\Auth;

class LoginResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?\App\Models\User $user = null,
    ) {}

    public static function success(?\App\Models\User $user = null): self
    {
        return new self('success', user: $user);
    }

    public static function passwordExpired(?\App\Models\User $user = null): self
    {
        return new self('password_expired', 'Your password has expired. Please set a new password.', $user);
    }

    public static function failed(string $message): self
    {
        return new self('failed', $message);
    }

    public static function locked(string $message): self
    {
        return new self('locked', $message);
    }

    public static function captchaRequired(string $message): self
    {
        return new self('captcha_required', $message);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isPasswordExpired(): bool
    {
        return $this->status === 'password_expired';
    }

    public function isCaptchaRequired(): bool
    {
        return $this->status === 'captcha_required';
    }
}
