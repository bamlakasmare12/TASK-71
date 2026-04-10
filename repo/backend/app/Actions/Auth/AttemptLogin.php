<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CaptchaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AttemptLogin
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private AuditService $audit,
        private CaptchaService $captcha,
    ) {}

    public function execute(
        string $username,
        string $password,
        ?string $captchaInput = null,
        bool $establishSession = true,
    ): LoginResult {
        $user = User::where('username', $username)->first();

        // Check if user exists
        if ($user === null) {
            $this->audit->log(AuditAction::LoginFailed, null, [
                'username' => $username,
                'reason' => 'user_not_found',
            ], 'warning');

            return LoginResult::failed('Invalid username or password.');
        }

        // Check if account is active
        if (!$user->is_active) {
            $this->audit->log(AuditAction::LoginFailed, $user->id, [
                'reason' => 'account_disabled',
            ], 'warning');

            return LoginResult::failed('Your account has been disabled. Contact an administrator.');
        }

        // Check lockout
        if ($user->isLocked()) {
            $minutesLeft = (int) now()->diffInMinutes($user->locked_until, false);

            return LoginResult::locked("Account is locked. Try again in {$minutesLeft} minute(s).");
        }

        // Check if CAPTCHA is required
        if ($user->requiresCaptcha() && $captchaInput !== null) {
            if (!$this->captcha->verify($captchaInput)) {
                $this->audit->log(AuditAction::CaptchaFailed, $user->id, [], 'warning');

                return LoginResult::captchaRequired('Invalid CAPTCHA. Please try again.');
            }
        }

        if ($user->requiresCaptcha() && $captchaInput === null) {
            return LoginResult::captchaRequired('Please complete the CAPTCHA to continue.');
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            $user->increment('failed_login_attempts');

            if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);
                $this->audit->log(AuditAction::AccountLocked, $user->id, [
                    'attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->locked_until->toIso8601String(),
                ], 'critical');

                return LoginResult::locked('Account locked for ' . self::LOCKOUT_MINUTES . ' minutes due to too many failed attempts.');
            }

            $this->audit->log(AuditAction::LoginFailed, $user->id, [
                'attempts' => $user->failed_login_attempts,
            ], 'warning');

            $needsCaptcha = $user->requiresCaptcha();

            return $needsCaptcha
                ? LoginResult::captchaRequired('Invalid username or password.')
                : LoginResult::failed('Invalid username or password.');
        }

        // Detect login anomaly (new device fingerprint)
        $currentFingerprint = md5(request()->userAgent() . request()->ip());
        if ($user->device_fingerprint !== null && $user->device_fingerprint !== $currentFingerprint) {
            $this->audit->log(AuditAction::LoginAnomaly, $user->id, [
                'previous_fingerprint' => $user->device_fingerprint,
                'new_fingerprint' => $currentFingerprint,
            ], 'warning');
        }

        // Reset failed attempts and login
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'device_fingerprint' => $currentFingerprint,
        ]);

        if ($establishSession) {
            Auth::login($user);
            session()->regenerate();
        }

        $this->audit->log(AuditAction::Login, $user->id);

        // Check if password rotation is needed
        if ($user->isPasswordExpired()) {
            return LoginResult::passwordExpired($user);
        }

        return LoginResult::success($user);
    }
}
