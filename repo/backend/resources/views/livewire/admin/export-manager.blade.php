<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Export Data</h1>

    @if ($errorMessage)
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
            <p class="text-sm text-red-700">{{ $errorMessage }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-8">
        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-6">
            <p class="text-sm text-amber-700">
                <strong>Security Notice:</strong> This action is protected by step-up authentication. Exported files never contain password hashes. Sensitive fields (phone, external IDs) are only included if explicitly enabled below.
            </p>
        </div>

        <form wire:submit="export" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data Type</label>
                <select wire:model.live="entity" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    <option value="services">Services</option>
                    <option value="users">Users</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="format" type="radio" value="csv" class="text-indigo-600 focus:ring-indigo-500">
                        CSV
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="format" type="radio" value="json" class="text-indigo-600 focus:ring-indigo-500">
                        JSON
                    </label>
                </div>
            </div>

            <div class="space-y-3 rounded-lg bg-gray-50 p-4">
                <div class="flex items-center gap-2">
                    <input wire:model.live="incrementalOnly" type="checkbox" id="incrementalOnly" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="incrementalOnly" class="text-sm text-gray-700">Incremental export (by last-updated timestamp)</label>
                </div>

                @if ($incrementalOnly)
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Export records updated since:</label>
                        <input wire:model="sinceDate" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                @endif
            </div>

            @if ($entity === 'users')
                <div class="rounded-lg border border-red-200 bg-red-50/50 p-4">
                    <div class="flex items-center gap-2">
                        <input wire:model="includeSensitive" type="checkbox" id="includeSensitive" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="includeSensitive" class="text-sm text-gray-700">Include sensitive fields (phone, external IDs)</label>
                    </div>
                    <p class="mt-1 ml-6 text-xs text-red-600">Sensitive data will be decrypted for export. Handle with care.</p>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                    class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-75">
                <span wire:loading.remove wire:target="export">Download Export</span>
                <span wire:loading wire:target="export" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generating export...
                </span>
            </button>
        </form>
    </div>
</div>
