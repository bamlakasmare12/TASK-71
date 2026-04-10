<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Services\CaptchaService;
use Livewire\Component;

class LoginForm extends Component
{
    public string $username = '';
    public string $password = '';
    public string $captchaInput = '';
    public bool $showCaptcha = false;
    public string $captchaImage = '';
    public string $errorMessage = '';

    protected function rules(): array
    {
        $rules = [
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ];

        if ($this->showCaptcha) {
            $rules['captchaInput'] = 'required|string';
        }

        return $rules;
    }

    public function mount(): void
    {
        if (auth()->check()) {
            $this->redirect(route('dashboard'));
        }
    }

    public function login(AttemptLogin $action, CaptchaService $captcha): void
    {
        $this->validate();
        $this->errorMessage = '';

        $result = $action->execute(
            $this->username,
            $this->password,
            $this->showCaptcha ? $this->captchaInput : null,
        );

        if ($result->isSuccess()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }

        if ($result->isPasswordExpired()) {
            $this->redirect(route('auth.password.change'), navigate: true);
            return;
        }

        if ($result->isCaptchaRequired()) {
            $this->showCaptcha = true;
            $this->captchaImage = $captcha->generate();
            $this->captchaInput = '';
        }

        $this->errorMessage = $result->message ?? 'An error occurred.';
        $this->password = '';
    }

    public function refreshCaptcha(CaptchaService $captcha): void
    {
        $this->captchaImage = $captcha->generate();
        $this->captchaInput = '';
    }

    public function render()
    {
        return view('livewire.auth.login-form')
            ->layout('components.layouts.guest', ['title' => 'Sign In']);
    }
}
