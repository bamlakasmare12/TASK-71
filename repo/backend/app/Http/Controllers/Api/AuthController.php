<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AttemptLogin;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, AttemptLogin $action): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'captcha' => 'nullable|string',
        ]);

        $result = $action->execute(
            $request->username,
            $request->password,
            $request->captcha,
            establishSession: true,
        );

        if ($result->isSuccess()) {
            return response()->json([
                'user' => new UserResource($result->user),
            ]);
        }

        if ($result->isPasswordExpired()) {
            return response()->json([
                'error' => 'password_expired',
                'message' => $result->message,
            ], 403);
        }

        if ($result->isCaptchaRequired()) {
            return response()->json([
                'error' => 'captcha_required',
                'message' => $result->message,
            ], 422);
        }

        // locked or failed
        $statusCode = $result->status === 'locked' ? 423 : 401;

        return response()->json([
            'error' => $result->status,
            'message' => $result->message,
        ], $statusCode);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $audit->log(AuditAction::Logout, $request->user()->id);

        \Illuminate\Support\Facades\Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function stepUpVerify(Request $request, AuditService $audit): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            $audit->log(AuditAction::StepUpFailed, $user->id, [], 'warning');
            return response()->json(['error' => 'Invalid password.'], 403);
        }

        $audit->log(AuditAction::StepUpVerified, $user->id);

        \App\Http\Middleware\StepUpAuth::elevate();

        return response()->json([
            'message' => 'Step-up verified.',
            'elevated_until' => now()->addMinutes(5)->toIso8601String(),
        ]);
    }
}
