<div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Identity Verification</h2>
                <p class="text-sm text-gray-500">This action requires additional verification.</p>
            </div>
        </div>

        @if ($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <p class="text-sm text-red-700">{{ $errorMessage }}</p>
            </div>
        @endif

        <form wire:submit="verify" class="space-y-5">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Enter your password</label>
                <input wire:model="password"
                       type="password"
                       id="password"
                       required
                       autocomplete="current-password"
                       autofocus
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <a href="{{ route('dashboard') }}"
                   class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors text-center">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75 cursor-wait"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-all disabled:opacity-75">
                    <span wire:loading.remove>Verify</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Verifying...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
