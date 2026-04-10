@php
    $user = auth()->user();
    $activeReservations = $user->reservations()->whereIn('status', ['pending', 'confirmed', 'checked_in'])->count();
    $favoriteCount = \App\Models\UserFavorite::where('user_id', $user->id)->count();
    $recentlyViewed = \App\Models\UserRecentlyViewed::where('user_id', $user->id)
        ->with('service')
        ->orderByDesc('viewed_at')
        ->limit(5)
        ->get();

    // Editor/Admin stats
    $totalServices = $user->isEditor() || $user->isAdmin() ? \App\Models\Service::count() : 0;
    $totalSlots = $user->isEditor() || $user->isAdmin()
        ? \App\Models\TimeSlot::where('start_time', '>=', now())->where('is_active', true)->count() : 0;

    // Admin stats
    $totalUsers = $user->isAdmin() ? \App\Models\User::count() : 0;
    $totalAuditLogs = $user->isAdmin() ? \App\Models\AuditLog::count() : 0;
    $pendingImports = $user->isAdmin()
        ? \App\Models\ImportBatch::whereIn('status', ['pending', 'mapping', 'processing', 'pending_review'])->count() : 0;
@endphp

<x-layouts.app title="Dashboard">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">Welcome back, {{ $user->name }}.</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                {{ $user->isAdmin() ? 'bg-purple-100 text-purple-700' : ($user->isEditor() ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') }}">
                {{ $user->role->label() }}
            </span>
        </div>

        {{-- Booking Freeze Warning (Learner) --}}
        @if ($user->isBookingFrozen())
            <div class="rounded-xl bg-red-50 border border-red-200 p-4">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <p class="text-sm font-medium text-red-800">
                        Your booking privileges are frozen until {{ $user->booking_frozen_until->format('M d, Y g:i A') }}.
                    </p>
                </div>
            </div>
        @endif

        {{-- ========== LEARNER DASHBOARD ========== --}}
        @if ($user->isLearner())
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('reservations') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $activeReservations }}</p>
                            <p class="text-sm text-gray-500">Active Reservations</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('catalog') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $favoriteCount }}</p>
                            <p class="text-sm text-gray-500">Favorited Services</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('catalog') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">Browse</p>
                            <p class="text-sm text-gray-500">Service Catalog</p>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">What you can do</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="{{ route('catalog') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Browse & Book Services</p>
                            <p class="text-xs text-gray-500">Search catalog, filter by type, book time slots</p>
                        </div>
                    </a>
                    <a href="{{ route('reservations') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Manage Reservations</p>
                            <p class="text-xs text-gray-500">Confirm, cancel, check-in, view history</p>
                        </div>
                    </a>
                    <a href="{{ route('auth.password.change') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Change Password</p>
                            <p class="text-xs text-gray-500">Update your credentials</p>
                        </div>
                    </a>
                </div>
            </div>
        @endif

        {{-- ========== EDITOR DASHBOARD ========== --}}
        @if ($user->isEditor())
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="{{ route('catalog') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-blue-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $totalServices }}</p>
                            <p class="text-sm text-gray-500">Total Services</p>
                        </div>
                    </div>
                </a>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $totalSlots }}</p>
                            <p class="text-sm text-gray-500">Upcoming Time Slots</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('services.create') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-blue-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-900">Create</p>
                            <p class="text-sm text-gray-500">New Service</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Editor Tools</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="{{ route('services.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Create Service</p>
                            <p class="text-xs text-gray-500">Add new catalog items with descriptions and pricing</p>
                        </div>
                    </a>
                    <a href="{{ route('catalog') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Edit Services & Time Slots</p>
                            <p class="text-xs text-gray-500">Manage descriptions, eligibility, and scheduling windows</p>
                        </div>
                    </a>
                </div>
            </div>
        @endif

        {{-- ========== ADMIN DASHBOARD ========== --}}
        @if ($user->isAdmin())
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $totalUsers }}</p>
                            <p class="text-sm text-gray-500">Users</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('catalog') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-purple-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $totalServices }}</p>
                            <p class="text-sm text-gray-500">Services</p>
                        </div>
                    </div>
                </a>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $totalAuditLogs }}</p>
                            <p class="text-sm text-gray-500">Audit Events</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('admin.import') }}" class="bg-white rounded-xl border border-gray-200 p-6 hover:border-purple-300 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $pendingImports }}</p>
                            <p class="text-sm text-gray-500">Pending Imports</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Admin Tools</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <a href="{{ route('catalog') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Service Catalog</p>
                            <p class="text-xs text-gray-500">Browse, create, edit services</p>
                        </div>
                    </a>
                    <a href="{{ route('services.create') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Create Service</p>
                            <p class="text-xs text-gray-500">Add new catalog items</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.import') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Import Data</p>
                            <p class="text-xs text-gray-500">CSV/JSON upload with duplicate detection</p>
                        </div>
                    </a>
                    <a href="{{ route('admin.export') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Export Data</p>
                            <p class="text-xs text-gray-500">CSV/JSON export (step-up auth required)</p>
                        </div>
                    </a>
                    <a href="{{ route('reservations') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Reservations</p>
                            <p class="text-xs text-gray-500">View all bookings and activity</p>
                        </div>
                    </a>
                    <a href="{{ route('auth.password.change') }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Security</p>
                            <p class="text-xs text-gray-500">Change password, manage credentials</p>
                        </div>
                    </a>
                </div>
            </div>
        @endif

        {{-- Recently Viewed (all roles) --}}
        @if ($recentlyViewed->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Recently Viewed Services</h2>
                <div class="space-y-3">
                    @foreach ($recentlyViewed as $rv)
                        <a href="{{ route('services.show', $rv->service) }}" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $rv->service->title }}</p>
                                <p class="text-xs text-gray-500">{{ $rv->service->service_type }} &middot; {{ $rv->service->is_free ? 'Free' : '$' . number_format($rv->service->price, 2) }}</p>
                            </div>
                            <span class="text-xs text-gray-400">{{ $rv->viewed_at->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Account Info (all roles) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Username</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Role</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $user->role->label() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Password Last Changed</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">
                        {{ $user->password_updated_at?->format('M d, Y') ?? 'Never' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Member Since</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ $user->created_at->format('M d, Y') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-layouts.app>
