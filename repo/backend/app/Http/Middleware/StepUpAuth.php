<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StepUpAuth
{
    private const SESSION_KEY = 'step_up_verified_at';
    private const ELEVATION_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $verifiedAt = session(self::SESSION_KEY);

        if ($verifiedAt === null || abs(now()->diffInMinutes($verifiedAt)) >= self::ELEVATION_MINUTES) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'step_up_required',
                    'message' => 'Please re-enter your password to continue.',
                ], 403);
            }

            session(['step_up_redirect' => $request->fullUrl()]);

            return redirect()->route('auth.step-up');
        }

        return $next($request);
    }

    public static function elevate(): void
    {
        session([self::SESSION_KEY => now()]);
    }

    public static function isElevated(): bool
    {
        $verifiedAt = session(self::SESSION_KEY);

        return $verifiedAt !== null && abs(now()->diffInMinutes($verifiedAt)) < self::ELEVATION_MINUTES;
    }
}
