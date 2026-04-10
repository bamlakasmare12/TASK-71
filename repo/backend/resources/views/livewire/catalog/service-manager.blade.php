<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('catalog') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Catalog</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-6">{{ $isEditing ? 'Edit Service' : 'Create Service' }}</h1>

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

        <form wire:submit="save" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input wire:model="title" type="text" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea wire:model="description" rows="4" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
                @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                    <select wire:model="serviceType" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="">Select type...</option>
                        @foreach ($serviceTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('serviceType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price ($)</label>
                    <input wire:model="price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                <div class="flex flex-wrap gap-3">
                    @foreach ($audiences as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input wire:model="targetAudience" type="checkbox" value="{{ $key }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Number</label>
                    <input wire:model="projectNumber" type="text" placeholder="e.g. PRJ-2026-001" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('projectNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patent Number</label>
                    <input wire:model="patentNumber" type="text" placeholder="e.g. PAT-12345" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    @error('patentNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                <input wire:model="tagsInput" type="text" placeholder="e.g. writing, data, statistics" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Eligibility Notes</label>
                <textarea wire:model="eligibilityNotes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input wire:model="isActive" type="checkbox" id="isActive" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="isActive" class="text-sm text-gray-700">Active (visible in catalog)</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('catalog') }}" class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75 cursor-wait"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-all disabled:opacity-75">
                    <span wire:loading.remove>{{ $isEditing ? 'Update Service' : 'Create Service' }}</span>
                    <span wire:loading>Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
