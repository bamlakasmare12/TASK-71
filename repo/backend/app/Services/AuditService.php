<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        AuditAction $action,
        ?int $userId = null,
        array $metadata = [],
        string $severity = 'info',
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_fingerprint' => $this->extractFingerprint($request),
            'metadata' => $metadata,
            'severity' => $severity,
            'created_at' => now(),
        ]);
    }

    private function extractFingerprint(Request $request): ?string
    {
        return $request->header('X-Device-Fingerprint')
            ?? md5($request->userAgent() . $request->ip());
    }
}
