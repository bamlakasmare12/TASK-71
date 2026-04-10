<div>
    <div class="mb-6">
        <a href="{{ route('services.show', $service) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to {{ $service->title }}</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Create Slot Form --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Add Time Slot</h2>

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

            <form wire:submit="createSlot" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input wire:model="startDate" type="date" required min="{{ now()->addDay()->format('Y-m-d') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input wire:model="startTime" type="time" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('startTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input wire:model="endTime" type="time" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('endTime') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                    <input wire:model="capacity" type="number" min="1" max="100" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('capacity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-75">
                    <span wire:loading.remove>Add Time Slot</span>
                    <span wire:loading>Adding...</span>
                </button>
            </form>
        </div>

        {{-- Existing Slots --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Slots</h2>

            @if ($slots->isEmpty())
                <div class="text-center py-8">
                    <p class="text-sm text-gray-500">No upcoming time slots.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($slots as $slot)
                        <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $slot->start_time->format('D, M d') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $slot->start_time->format('g:i A') }} - {{ $slot->end_time->format('g:i A') }}
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ $slot->booked_count }}/{{ $slot->capacity }} booked
                                </div>
                            </div>
                            <button wire:click="deactivateSlot({{ $slot->id }})"
                                    wire:confirm="Deactivate this time slot?"
                                    class="text-xs text-red-500 hover:text-red-700 transition-colors">
                                Deactivate
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
