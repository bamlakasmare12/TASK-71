<?php

namespace App\Http\Middleware;

use App\Enums\AuditAction;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    private const IDLE_MINUTES = 20;
    private const SESSION_KEY = 'last_activity_at';

    public function __construct(private AuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $lastActivity = session(self::SESSION_KEY);

        if ($lastActivity !== null && now()->diffInMinutes($lastActivity) >= self::IDLE_MINUTES) {
            $userId = Auth::id();

            $this->audit->log(AuditAction::SessionTimeout, $userId, [
                'last_activity' => $lastActivity->toIso8601String(),
            ]);

            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'session_timeout',
                    'message' => 'Your session has expired due to inactivity.',
                ], 401);
            }

            return redirect()->route('login')->with('warning', 'Your session has expired due to inactivity.');
        }

        session([self::SESSION_KEY => now()]);

        return $next($request);
    }
}
