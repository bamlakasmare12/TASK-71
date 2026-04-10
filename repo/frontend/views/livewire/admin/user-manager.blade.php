<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
            <p class="text-sm text-red-700">{{ $errorMessage }}</p>
        </div>
    @endif

    @if ($successMessage)
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3">
            <p class="text-sm text-green-700">{{ $successMessage }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search by name, username, or email..."
                   class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <select wire:model.live="roleFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                <option value="">All Roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $user->username }} &middot; {{ $user->email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if ($user->id !== auth()->id())
                                <select wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                        class="rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" {{ $user->role === $role ? 'selected' : '' }}>
                                            {{ $role->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                    {{ $user->role->label() }} (you)
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if ($user->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Deactivated</span>
                            @endif
                            @if ($user->isBookingFrozen())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 ml-1">Frozen</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if ($user->id !== auth()->id() && $user->is_active)
                                <button wire:click="deleteAccount({{ $user->id }})"
                                        wire:confirm="Are you sure you want to deactivate this account? This requires step-up verification."
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                                    Deactivate
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
