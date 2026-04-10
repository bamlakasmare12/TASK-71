<div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Sign in to your account</h2>

        @if ($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <p class="text-sm text-red-700">{{ $errorMessage }}</p>
            </div>
        @endif

        <form wire:submit="login" class="space-y-5">
            {{-- Username --}}
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input wire:model="username"
                       type="text"
                       id="username"
                       autocomplete="username"
                       required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Enter your username">
                @error('username')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input wire:model="password"
                       type="password"
                       id="password"
                       autocomplete="current-password"
                       required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Enter your password">
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- CAPTCHA --}}
            @if ($showCaptcha)
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium text-gray-700">Security Verification</label>
                        <button type="button"
                                wire:click="refreshCaptcha"
                                class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors">
                            Refresh
                        </button>
                    </div>
                    @if ($captchaImage)
                        <div class="flex justify-center">
                            <img src="{{ $captchaImage }}" alt="CAPTCHA" class="rounded border border-gray-200">
                        </div>
                    @endif
                    <input wire:model="captchaInput"
                           type="text"
                           placeholder="Enter the characters shown above"
                           autocomplete="off"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors">
                    @error('captchaInput')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Submit --}}
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-wait"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all disabled:opacity-75">
                <span wire:loading.remove>Sign In</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Signing in...
                </span>
            </button>
        </form>
    </div>

    <p class="text-center text-sm text-gray-500 mt-6">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-800 font-medium" wire:navigate>Create one</a>
    </p>
</div>
