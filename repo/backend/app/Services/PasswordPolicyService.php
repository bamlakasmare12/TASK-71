<?php

namespace App\Services;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyService
{
    public const MIN_LENGTH = 12;
    public const HISTORY_COUNT = 5;
    public const ROTATION_DAYS = 90;

    public function validate(string $password, ?User $user = null): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        if ($user !== null && $this->matchesPreviousPasswords($password, $user)) {
            $errors[] = 'Password cannot match any of your last ' . self::HISTORY_COUNT . ' passwords.';
        }

        return $errors;
    }

    public function recordPassword(User $user, string $hashedPassword): void
    {
        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $hashedPassword,
            'created_at' => now(),
        ]);

        // Keep only the last HISTORY_COUNT entries
        $toDelete = PasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->skip(self::HISTORY_COUNT)
            ->pluck('id');

        if ($toDelete->isNotEmpty()) {
            PasswordHistory::whereIn('id', $toDelete)->delete();
        }
    }

    private function matchesPreviousPasswords(string $password, User $user): bool
    {
        $histories = PasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_COUNT)
            ->pluck('password_hash');

        foreach ($histories as $hash) {
            if (Hash::check($password, $hash)) {
                return true;
            }
        }

        return false;
    }
}
