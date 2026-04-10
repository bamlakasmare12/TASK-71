<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PasswordPolicyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class RegisterForm extends Component
{
    public string $username = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    public string $errorMessage = '';
    public array $policyErrors = [];

    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(route('dashboard'));
        }
    }

    public function register(PasswordPolicyService $policy, AuditService $audit): void
    {
        $this->validate([
            'username' => 'required|string|min:3|max:100|alpha_dash|unique:users,username',
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:12',
            'passwordConfirmation' => 'required|same:password',
        ]);

        $this->errorMessage = '';
        $this->policyErrors = [];

        // Validate password against policy
        $errors = $policy->validate($this->password);
        if (!empty($errors)) {
            $this->policyErrors = $errors;
            return;
        }

        $hashedPassword = Hash::make($this->password);

        $user = User::create([
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $hashedPassword,
            'created_at' => now(),
        ]);

        $audit->log(\App\Enums\AuditAction::Login, $user->id, [
            'action' => 'account_created',
            'role' => 'learner',
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register-form')
            ->layout('components.layouts.guest', ['title' => 'Create Account']);
    }
}
