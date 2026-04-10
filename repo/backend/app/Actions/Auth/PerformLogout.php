<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerformLogout
{
    public function __construct(private AuditService $audit) {}

    public function execute(bool $allDevices = true): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $currentSessionId = session()->getId();

        if ($allDevices) {
            // Purge ALL sessions for this user (single-logout across devices)
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            $this->audit->log(AuditAction::LogoutAll, $user->id);
        }

        $this->audit->log(AuditAction::Logout, $user->id);

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
