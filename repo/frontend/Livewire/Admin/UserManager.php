<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Services\InternalApiClient;
use App\Services\UserQueryService;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $errorMessage = '';
    public string $successMessage = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function changeRole(int $userId, string $newRole, InternalApiClient $api): void
    {
        $this->clearMessages();

        $response = $api->put("admin/users/{$userId}/role", [
            'role' => $newRole,
        ]);

        if ($response['ok']) {
            $userData = $response['data'];
            $this->successMessage = "Role changed to {$newRole} successfully.";
        } elseif ($response['status'] === 403) {
            // Step-up required — redirect
            session(['step_up_redirect' => route('admin.users')]);
            $this->redirect(route('auth.step-up'));
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to change role.';
        }
    }

    public function deleteAccount(int $userId, InternalApiClient $api): void
    {
        $this->clearMessages();

        $response = $api->delete("admin/users/{$userId}");

        if ($response['ok']) {
            $this->successMessage = 'Account has been deactivated.';
        } elseif ($response['status'] === 403) {
            // Step-up required — redirect
            session(['step_up_redirect' => route('admin.users')]);
            $this->redirect(route('auth.step-up'));
        } elseif ($response['status'] === 422) {
            $this->errorMessage = $response['error'] ?? 'Cannot deactivate this account.';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to deactivate account.';
        }
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function render(UserQueryService $userQuery)
    {
        return view('livewire.admin.user-manager', [
            'users' => $userQuery->listUsers([
                'search' => $this->search,
                'role' => $this->roleFilter,
            ]),
            'roles' => UserRole::cases(),
        ])->layout('components.layouts.app', ['title' => 'User Management']);
    }
}
