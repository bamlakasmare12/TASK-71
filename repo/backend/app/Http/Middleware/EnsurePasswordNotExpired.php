<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isPasswordExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'password_expired',
                    'message' => 'Your password has expired. Please change it.',
                ], 403);
            }

            return redirect()->route('auth.password.change')
                ->with('warning', 'Your password has expired. Please set a new password.');
        }

        return $next($request);
    }
}
