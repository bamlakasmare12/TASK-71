<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} - ResearchHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 antialiased" x-data="{ sessionWarning: false }" x-init="
    let idleTimer;
    const WARN_AFTER = 18 * 60 * 1000;
    function resetTimer() {
        clearTimeout(idleTimer);
        sessionWarning = false;
        idleTimer = setTimeout(() => { sessionWarning = true; }, WARN_AFTER);
    }
    ['mousemove','keydown','click','scroll'].forEach(e => document.addEventListener(e, resetTimer));
    resetTimer();
">
    {{-- Session Timeout Warning Modal --}}
    <div x-show="sessionWarning" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm mx-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Session Expiring</h3>
            </div>
            <p class="text-sm text-gray-600 mb-4">Your session will expire in 2 minutes due to inactivity. Move your mouse or press any key to stay logged in.</p>
            <button @click="sessionWarning = false; fetch('/api/ping', {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}})"
                    class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Stay Logged In
            </button>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <span class="font-semibold text-gray-900">ResearchHub</span>
                    </a>
                    <div class="hidden sm:flex items-center gap-4">
                        <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Dashboard</a>
                        <a href="{{ route('catalog') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Catalog</a>

                        {{-- Learner: reservations --}}
                        @if (auth()->user()?->isLearner())
                            <a href="{{ route('reservations') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">My Reservations</a>
                        @endif

                        {{-- Editor: services + scheduling --}}
                        @if (auth()->user()?->isEditor() || auth()->user()?->isAdmin())
                            <a href="{{ route('services.create') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">New Service</a>
                        @endif

                        {{-- Admin: full suite --}}
                        @if (auth()->user()?->isAdmin())
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('reservations') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Reservations</a>
                            <a href="{{ route('admin.import') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Import</a>
                            <a href="{{ route('admin.export') }}" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Export</a>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-600">
                        <span class="font-medium">{{ auth()->user()?->name }}</span>
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                            {{ auth()->user()?->role?->label() }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if (session('warning'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4">
                <p class="text-sm text-amber-800">{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    {{-- Main Content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
