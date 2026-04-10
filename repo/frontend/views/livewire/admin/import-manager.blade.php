<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Import Data</h1>

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

    {{-- Step Indicator --}}
    <div class="flex items-center gap-2 mb-8">
        @foreach (['upload' => 'Upload', 'mapping' => 'Map Fields', 'processing' => 'Processing', 'review' => 'Review', 'done' => 'Complete'] as $key => $label)
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                    {{ $step === $key ? 'bg-indigo-600 text-white' : ($this->isStepComplete($key) ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                    {{ $loop->iteration }}
                </div>
                <span class="text-sm {{ $step === $key ? 'font-semibold text-gray-900' : 'text-gray-500' }}">{{ $label }}</span>
                @if (!$loop->last)
                    <div class="w-8 h-px bg-gray-300"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- STEP 1: Upload --}}
    @if ($step === 'upload')
        <div class="bg-white rounded-xl border border-gray-200 p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Upload File</h2>

            <form wire:submit="upload" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Type</label>
                    <select wire:model="entity" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="services">Services</option>
                        <option value="users">Users</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conflict Resolution Strategy</label>
                    <select wire:model="conflictStrategy" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="prefer_newest">Prefer Newest (by updated_at)</option>
                        <option value="admin_override">Manual Review (Admin Override)</option>
                        <option value="skip">Skip Duplicates</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Determines how duplicate records are handled during import.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File (CSV or JSON, max 10MB)</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 transition-colors"
                         x-data="{ dragging: false }"
                         x-on:dragover.prevent="dragging = true"
                         x-on:dragleave="dragging = false"
                         x-on:drop.prevent="dragging = false"
                         :class="{ 'border-indigo-400 bg-indigo-50': dragging }">
                        <input type="file" wire:model="file" accept=".csv,.json,.txt" class="hidden" id="fileInput">
                        <label for="fileInput" class="cursor-pointer">
                            <svg class="mx-auto h-10 w-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="text-sm text-gray-600">Click to browse or drag & drop</p>
                            <p class="text-xs text-gray-400 mt-1">CSV or JSON files</p>
                        </label>
                    </div>

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="file" class="mt-3">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full animate-pulse" style="width: 60%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Uploading file...</p>
                    </div>

                    @if ($file)
                        <div class="mt-3 flex items-center gap-2 text-sm text-green-700 bg-green-50 rounded-lg p-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $file->getClientOriginalName() }} ({{ number_format($file->getSize() / 1024, 1) }} KB)
                        </div>
                    @endif

                    @error('file') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-75">
                    <span wire:loading.remove wire:target="upload">Continue to Field Mapping</span>
                    <span wire:loading wire:target="upload">Parsing file...</span>
                </button>
            </form>
        </div>
    @endif

    {{-- STEP 2: Field Mapping --}}
    @if ($step === 'mapping')
        <div class="bg-white rounded-xl border border-gray-200 p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Map Fields</h2>
            <p class="text-sm text-gray-500 mb-6">Map each source column to a destination field. Leave unmapped to skip.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-medium text-gray-700">Source Column</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-400">→</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-700">Destination Field</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sourceHeaders as $header)
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <code class="text-sm bg-gray-100 px-2 py-0.5 rounded">{{ $header }}</code>
                                </td>
                                <td class="text-center py-3 px-4 text-gray-300">→</td>
                                <td class="py-3 px-4">
                                    <select wire:model="fieldMapping.{{ $header }}"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                                        <option value="">-- Skip --</option>
                                        @foreach ($destinationFields as $field)
                                            <option value="{{ $field }}">{{ $field }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                <button wire:click="$set('step', 'upload')" class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Back
                </button>
                <button wire:click="startProcessing" wire:loading.attr="disabled"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-75">
                    <span wire:loading.remove wire:target="startProcessing">Start Import</span>
                    <span wire:loading wire:target="startProcessing">Starting...</span>
                </button>
            </div>
        </div>
    @endif

    {{-- STEP 3: Processing --}}
    @if ($step === 'processing' && $batch)
        <div class="bg-white rounded-xl border border-gray-200 p-8" wire:poll.2s="refreshProgress">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Processing Import</h2>

            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Progress</span>
                        <span class="font-medium text-gray-900">{{ $batch->processed_rows }}/{{ $batch->total_rows }} rows</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500"
                             style="width: {{ $batch->progressPercent() }}%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg bg-green-50 p-4 text-center">
                        <p class="text-2xl font-bold text-green-700">{{ $batch->success_count }}</p>
                        <p class="text-xs text-green-600">Imported</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-4 text-center">
                        <p class="text-2xl font-bold text-amber-700">{{ $batch->duplicate_count }}</p>
                        <p class="text-xs text-amber-600">Conflicts</p>
                    </div>
                    <div class="rounded-lg bg-red-50 p-4 text-center">
                        <p class="text-2xl font-bold text-red-700">{{ $batch->error_count }}</p>
                        <p class="text-xs text-red-600">Errors</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="animate-spin h-4 w-4 text-indigo-600" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Processing... This page will update automatically.
                </div>
            </div>
        </div>
    @endif

    {{-- STEP 4: Conflict Review --}}
    @if ($step === 'review' && $batch)
        <div class="bg-white rounded-xl border border-gray-200 p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Review Conflicts</h2>
            <p class="text-sm text-gray-500 mb-6">{{ $conflicts->count() }} conflict(s) require your decision.</p>

            <div class="space-y-6">
                @foreach ($conflicts as $conflict)
                    <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $conflict->match_type === 'exact_id' ? 'Exact Match' : 'Similar Title' }}
                                </span>
                                <span class="ml-2 text-xs text-gray-500">
                                    Similarity: {{ number_format($conflict->similarity_score * 100, 1) }}%
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Incoming Data</h4>
                                <div class="bg-white rounded-lg border border-gray-200 p-3 text-xs space-y-1">
                                    @foreach ($conflict->incoming_data as $key => $val)
                                        <div><span class="font-medium text-gray-700">{{ $key }}:</span> <span class="text-gray-600">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Existing Record</h4>
                                <div class="bg-white rounded-lg border border-gray-200 p-3 text-xs space-y-1">
                                    @foreach (collect($conflict->existing_data)->only(array_keys($conflict->incoming_data)) as $key => $val)
                                        <div><span class="font-medium text-gray-700">{{ $key }}:</span> <span class="text-gray-600">{{ is_array($val) ? json_encode($val) : $val }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="resolveConflict({{ $conflict->id }}, 'overwrite')"
                                    class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                Overwrite with Incoming
                            </button>
                            <button wire:click="resolveConflict({{ $conflict->id }}, 'skip')"
                                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Keep Existing
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <button wire:click="finishReview"
                        class="px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                    Finish Import
                </button>
            </div>
        </div>
    @endif

    {{-- STEP 5: Done --}}
    @if ($step === 'done' && $batch)
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Import Complete</h2>
            <p class="text-sm text-gray-500 mb-6">{{ $batch->filename }}</p>

            <div class="grid grid-cols-4 gap-4 mb-6 max-w-lg mx-auto">
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $batch->total_rows }}</p>
                    <p class="text-xs text-gray-500">Total</p>
                </div>
                <div class="rounded-lg bg-green-50 p-3 text-center">
                    <p class="text-lg font-bold text-green-700">{{ $batch->success_count }}</p>
                    <p class="text-xs text-green-600">Success</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-3 text-center">
                    <p class="text-lg font-bold text-amber-700">{{ $batch->duplicate_count }}</p>
                    <p class="text-xs text-amber-600">Duplicates</p>
                </div>
                <div class="rounded-lg bg-red-50 p-3 text-center">
                    <p class="text-lg font-bold text-red-700">{{ $batch->error_count }}</p>
                    <p class="text-xs text-red-600">Errors</p>
                </div>
            </div>

            {{-- Error Log --}}
            @if (!empty($batch->error_log))
                <div class="text-left max-w-lg mx-auto mb-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Error Log</h3>
                    <div class="bg-red-50 rounded-lg border border-red-200 p-4 max-h-48 overflow-y-auto">
                        @foreach ($batch->error_log as $err)
                            <div class="text-xs text-red-700 mb-1">
                                <span class="font-medium">Row {{ $err['row'] }}:</span> {{ $err['error'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <button wire:click="startNew" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Import Another File
            </button>
        </div>
    @endif
</div>

@php
    // Helper for step indicator - called in blade context
@endphp
