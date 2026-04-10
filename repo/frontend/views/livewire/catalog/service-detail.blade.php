<div>
    <div class="mb-6">
        <a href="{{ route('catalog') }}" class="text-sm text-indigo-600 hover:text-indigo-800 transition-colors">&larr; Back to Catalog</a>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Service Info --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div class="flex items-start justify-between mb-4">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $service->title }}</h1>
                    <button wire:click="toggleFavorite"
                            class="p-2 rounded-full hover:bg-gray-100 transition-colors"
                            title="{{ $isFavorited ? 'Remove from favorites' : 'Add to favorites' }}">
                        @if ($isFavorited)
                            <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        @else
                            <svg class="w-6 h-6 text-gray-300 hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        @endif
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $service->is_free ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                        {{ $service->is_free ? 'Free' : '$' . number_format($service->price, 2) }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                        {{ $service->service_type }}
                    </span>
                    @foreach ($service->tags as $tag)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>

                <div class="prose prose-sm text-gray-600 max-w-none mb-6">
                    {!! nl2br(e($service->description)) !!}
                </div>

                @if ($service->eligibility_notes)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4">
                        <h3 class="text-sm font-medium text-amber-800 mb-1">Eligibility Notes</h3>
                        <p class="text-sm text-amber-700">{{ $service->eligibility_notes }}</p>
                    </div>
                @endif

                @if (!empty($service->target_audience))
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Target Audience</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($service->target_audience as $aud)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                    {{ ucfirst($aud) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Time Slots sidebar --}}
        <div>
            <div class="bg-white rounded-xl border border-gray-200 p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Available Time Slots</h2>

                @if ($service->timeSlots->isEmpty())
                    <div class="text-center py-8">
                        <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No time slots currently available.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($service->timeSlots->take(8) as $slot)
                            <div class="rounded-lg border border-gray-200 p-3 hover:border-indigo-300 transition-colors">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $slot->start_time->format('D, M d') }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $slot->start_time->format('g:i A') }} - {{ $slot->end_time->format('g:i A') }}
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs {{ $slot->hasAvailability() ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $slot->capacity - $slot->booked_count }}/{{ $slot->capacity }} spots
                                    </span>
                                    @if ($slot->hasAvailability())
                                        @can('create', App\Models\Reservation::class)
                                            <button wire:click="bookSlot({{ $slot->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="px-3 py-1 text-xs font-medium bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                                <span wire:loading.remove wire:target="bookSlot({{ $slot->id }})">Book</span>
                                                <span wire:loading wire:target="bookSlot({{ $slot->id }})">...</span>
                                            </button>
                                        @else
                                            <span class="px-3 py-1 text-xs font-medium bg-gray-100 text-gray-400 rounded" title="Only learners can book">View Only</span>
                                        @endcan
                                    @else
                                        <span class="px-3 py-1 text-xs font-medium bg-gray-100 text-gray-400 rounded">Full</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
