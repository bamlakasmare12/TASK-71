<?php

namespace App\Livewire\Auth;

use App\Enums\AuditAction;
use App\Http\Middleware\StepUpAuth;
use App\Services\AuditService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class StepUpVerification extends Component
{
    public string $password = '';
    public string $errorMessage = '';

    public function verify(AuditService $audit): void
    {
        $this->validate([
            'password' => 'required|string',
        ]);

        $this->errorMessage = '';

        $user = auth()->user();

        if (!Hash::check($this->password, $user->password)) {
            $audit->log(AuditAction::StepUpFailed, $user->id, [], 'warning');
            $this->errorMessage = 'Password is incorrect.';
            $this->password = '';
            return;
        }

        StepUpAuth::elevate();
        $audit->log(AuditAction::StepUpVerified, $user->id);

        $redirect = session()->pull('step_up_redirect', route('dashboard'));
        $this->redirect($redirect, navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.step-up-verification')
            ->layout('components.layouts.app', ['title' => 'Verify Identity']);
    }
}
