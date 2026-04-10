<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ChangePassword;
use Livewire\Component;

class ChangePasswordForm extends Component
{
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';
    public array $errors_list = [];
    public bool $success = false;
    public bool $isExpired = false;

    public function mount(): void
    {
        $this->isExpired = auth()->user()?->isPasswordExpired() ?? false;
    }

    public function changePassword(ChangePassword $action): void
    {
        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:12',
            'newPasswordConfirmation' => 'required|same:newPassword',
        ]);

        $this->errors_list = [];
        $this->success = false;

        $result = $action->execute(
            auth()->user(),
            $this->currentPassword,
            $this->newPassword,
        );

        if ($result->success) {
            $this->success = true;
            $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);

            if ($this->isExpired) {
                $this->redirect(route('dashboard'), navigate: true);
            }
            return;
        }

        $this->errors_list = $result->errors;
    }

    public function render()
    {
        return view('livewire.auth.change-password-form')
            ->layout('components.layouts.app', ['title' => 'Change Password']);
    }
}
