<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PasswordPolicyService;
use Illuminate\Support\Facades\Hash;

class ChangePassword
{
    public function __construct(
        private PasswordPolicyService $policy,
        private AuditService $audit,
    ) {}

    public function execute(User $user, string $currentPassword, string $newPassword): ChangePasswordResult
    {
        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            $this->audit->log(AuditAction::PasswordChange, $user->id, [
                'success' => false,
                'reason' => 'current_password_mismatch',
            ], 'warning');

            return ChangePasswordResult::failed(['Current password is incorrect.']);
        }

        // Validate new password against policy
        $errors = $this->policy->validate($newPassword, $user);

        if (!empty($errors)) {
            return ChangePasswordResult::failed($errors);
        }

        // Hash and save
        $hashedPassword = Hash::make($newPassword);

        $this->policy->recordPassword($user, $hashedPassword);

        $user->update([
            'password' => $newPassword, // Will be hashed by cast
            'password_updated_at' => now(),
        ]);

        $this->audit->log(AuditAction::PasswordChange, $user->id, [
            'success' => true,
        ]);

        return ChangePasswordResult::success();
    }
}
