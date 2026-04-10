<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Change Password</h2>

        @if ($isExpired)
            <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-3">
                <p class="text-sm text-amber-700">Your password has expired. You must set a new password to continue.</p>
            </div>
        @endif

        @if ($success)
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3">
                <p class="text-sm text-green-700">Password changed successfully!</p>
            </div>
        @endif

        @if (count($errors_list) > 0)
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                    @foreach ($errors_list as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-sm text-gray-500 mb-6">Password must be at least 12 characters and include uppercase, lowercase, number, and special character.</p>

        <form wire:submit="changePassword" class="space-y-5">
            <div>
                <label for="currentPassword" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input wire:model="currentPassword"
                       type="password"
                       id="currentPassword"
                       required
                       autocomplete="current-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
                @error('currentPassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input wire:model="newPassword"
                       type="password"
                       id="newPassword"
                       required
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
                @error('newPassword')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="newPasswordConfirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input wire:model="newPasswordConfirmation"
                       type="password"
                       id="newPasswordConfirmation"
                       required
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
                @error('newPasswordConfirmation')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-wait"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all disabled:opacity-75">
                <span wire:loading.remove>Update Password</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Updating...
                </span>
            </button>
        </form>
    </div>
</div>
