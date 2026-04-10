<div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-6">Create your account</h2>

        @if ($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <p class="text-sm text-red-700">{{ $errorMessage }}</p>
            </div>
        @endif

        @if (count($policyErrors) > 0)
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                    @foreach ($policyErrors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit="register" class="space-y-5">
            {{-- Username --}}
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input wire:model="username" type="text" id="username" required autocomplete="username"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Choose a username">
                @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Full Name --}}
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input wire:model="name" type="text" id="name" required autocomplete="name"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Your full name">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input wire:model="email" type="email" id="email" required autocomplete="email"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="you@organization.local">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input wire:model="password" type="password" id="password" required autocomplete="new-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Min 12 chars, upper, lower, number, special">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="passwordConfirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input wire:model="passwordConfirmation" type="password" id="passwordConfirmation" required autocomplete="new-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                       placeholder="Re-enter your password">
                @error('passwordConfirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Submit --}}
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-75 cursor-wait"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all disabled:opacity-75">
                <span wire:loading.remove>Create Account</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Creating account...
                </span>
            </button>
        </form>
    </div>

    <p class="text-center text-sm text-gray-500 mt-6">
        Already have an account?
        <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-800 font-medium" wire:navigate>Sign in</a>
    </p>
</div>
