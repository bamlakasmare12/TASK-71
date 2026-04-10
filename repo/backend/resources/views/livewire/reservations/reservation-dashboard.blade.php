<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Reservations</h1>
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

    {{-- Filter Tabs --}}
    <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $filter === $key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($reservations->isEmpty())
        <div class="text-center py-16 bg-white rounded-xl border border-gray-200">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <h3 class="mt-3 text-sm font-medium text-gray-900">No reservations</h3>
            <p class="mt-1 text-sm text-gray-500">Browse the <a href="{{ route('catalog') }}" class="text-indigo-600 hover:underline">service catalog</a> to make a booking.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($reservations as $reservation)
                @php
                    $statusColor = $reservation->status->color();
                    $slot = $reservation->timeSlot;
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-6 hover:border-gray-300 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-base font-semibold text-gray-900">
                                    {{ $reservation->service->title }}
                                </h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700">
                                    {{ $reservation->status->label() }}
                                </span>
                            </div>

                            @if ($slot)
                                <div class="flex items-center gap-4 text-sm text-gray-500">
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ $slot->start_time->format('D, M d, Y') }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $slot->start_time->format('g:i A') }} - {{ $slot->end_time->format('g:i A') }}
                                    </span>
                                </div>
                            @endif

                            {{-- Pending expiry countdown --}}
                            @if ($reservation->isPending() && $reservation->expires_at)
                                <div class="mt-2 text-xs text-amber-600" x-data="{ remaining: {{ $reservation->expires_at->diffInSeconds(now()) }} }"
                                     x-init="setInterval(() => { remaining--; if(remaining <= 0) $wire.$refresh(); }, 1000)">
                                    Expires in <span x-text="Math.max(0, Math.floor(remaining/60)) + ':' + String(Math.max(0, remaining%60)).padStart(2,'0')"></span>
                                </div>
                            @endif

                            {{-- Penalties --}}
                            @if ($reservation->penalties->isNotEmpty())
                                <div class="mt-2 text-xs text-red-600">
                                    @foreach ($reservation->penalties as $penalty)
                                        Penalty: {{ $penalty->reason }}
                                        @if ($penalty->fee_amount > 0)
                                            (${{ number_format($penalty->fee_amount, 2) }})
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-2 ml-4">
                            @if ($reservation->isPending())
                                <button wire:click="confirm({{ $reservation->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                                    Confirm
                                </button>
                            @endif

                            @if ($reservation->canCancel())
                                <button wire:click="cancel({{ $reservation->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="Are you sure you want to cancel this reservation?{{ $reservation->isFreeCancellation() ? '' : ' A $25.00 late cancellation fee or 50 points deduction will apply.' }}"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50">
                                    Cancel
                                </button>
                                <button wire:click="startReschedule({{ $reservation->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-medium bg-amber-50 text-amber-700 rounded-lg hover:bg-amber-100 transition-colors disabled:opacity-50">
                                    Reschedule
                                </button>
                            @endif

                            @if ($reservation->canCheckIn())
                                <button wire:click="checkIn({{ $reservation->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                                    Check In
                                </button>
                            @elseif ($reservation->isConfirmed() && $slot && !$slot->isCheckInWindowOpen())
                                <span class="px-3 py-1.5 text-xs text-gray-400 rounded-lg bg-gray-50 cursor-not-allowed"
                                      title="Check-in opens 15 minutes before start time">
                                    Check-in {{ $slot->start_time->isFuture() ? 'opens ' . $slot->start_time->copy()->subMinutes(15)->format('g:i A') : 'closed' }}
                                </span>
                            @endif

                            @if (in_array($reservation->status->value, ['checked_in', 'partial_attendance']))
                                <button wire:click="checkOut({{ $reservation->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-3 py-1.5 text-xs font-medium bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-50">
                                    Check Out
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Reschedule Slot Picker --}}
        @if ($reschedulingReservationId)
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-amber-900">Select a new time slot</h3>
                    <button wire:click="cancelReschedule" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                </div>
                <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
                    @foreach ($availableSlots as $slot)
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors {{ $selectedNewSlotId == $slot['id'] ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">
                            <input type="radio" wire:model="selectedNewSlotId" value="{{ $slot['id'] }}" class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-900">{{ $slot['label'] }}</span>
                            <span class="text-xs text-gray-500 ml-auto">{{ $slot['available'] }} spot{{ $slot['available'] > 1 ? 's' : '' }} left</span>
                        </label>
                    @endforeach
                </div>
                <button wire:click="confirmReschedule"
                        wire:loading.attr="disabled"
                        {{ !$selectedNewSlotId ? 'disabled' : '' }}
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Confirm Reschedule
                </button>
            </div>
        @endif

        <div class="mt-6">
            {{ $reservations->links() }}
        </div>
    @endif
</div>
