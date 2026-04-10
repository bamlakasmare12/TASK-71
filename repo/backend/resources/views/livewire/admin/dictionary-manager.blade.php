<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dictionary & Form Rules</h1>
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

    {{-- Tab Navigation --}}
    <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
        <button wire:click="$set('activeTab', 'dictionaries')"
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'dictionaries' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Data Dictionaries
        </button>
        <button wire:click="$set('activeTab', 'form_rules')"
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'form_rules' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Form Rules
        </button>
    </div>

    {{-- Data Dictionaries Tab --}}
    @if ($activeTab === 'dictionaries')
        <div class="mb-4">
            <button wire:click="openDictForm" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                + Add Dictionary Entry
            </button>
        </div>

        {{-- Dictionary Form Modal --}}
        @if ($showDictForm)
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $editingDictId ? 'Edit' : 'Add' }} Dictionary Entry</h3>
                <form wire:submit="saveDictionary" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <input wire:model="dictType" type="text" placeholder="e.g., service_type, eligibility"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" {{ $editingDictId ? 'readonly' : '' }}>
                        @error('dictType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Key</label>
                        <input wire:model="dictKey" type="text" placeholder="e.g., consultation, graduate"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('dictKey') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                        <input wire:model="dictLabel" type="text" placeholder="Display label"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('dictLabel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input wire:model="dictSortOrder" type="number" min="0"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="flex items-center gap-2 sm:col-span-2">
                        <input wire:model="dictIsActive" type="checkbox" id="dictActive" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="dictActive" class="text-sm text-gray-700">Active</label>
                    </div>
                    <div class="flex gap-3 sm:col-span-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            {{ $editingDictId ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" wire:click="$set('showDictForm', false)" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Key</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($dictionaries as $dict)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm text-gray-900 font-medium">{{ $dict->type }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600 font-mono">{{ $dict->key }}</td>
                            <td class="px-6 py-3 text-sm text-gray-900">{{ $dict->label }}</td>
                            <td class="px-6 py-3 text-sm text-gray-500">{{ $dict->sort_order }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $dict->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $dict->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button wire:click="openDictForm({{ $dict->id }})" class="text-indigo-600 hover:text-indigo-800 text-sm mr-2">Edit</button>
                                <button wire:click="deleteDictionary({{ $dict->id }})" wire:confirm="Delete this dictionary entry?" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No dictionary entries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $dictionaries->links() }}</div>
    @endif

    {{-- Form Rules Tab --}}
    @if ($activeTab === 'form_rules')
        <div class="mb-4">
            <button wire:click="openRuleForm" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                + Add Form Rule
            </button>
        </div>

        @if ($showRuleForm)
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $editingRuleId ? 'Edit' : 'Add' }} Form Rule</h3>
                <form wire:submit="saveFormRule" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Entity</label>
                        <input wire:model="ruleEntity" type="text" placeholder="e.g., service, reservation"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('ruleEntity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Field</label>
                        <input wire:model="ruleField" type="text" placeholder="e.g., title, description"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @error('ruleField') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select wire:model="ruleType" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                            <option value="string">String</option>
                            <option value="integer">Integer</option>
                            <option value="numeric">Numeric</option>
                            <option value="email">Email</option>
                            <option value="date">Date</option>
                            <option value="boolean">Boolean</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input wire:model="ruleRequired" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">Required</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input wire:model="ruleIsActive" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min</label>
                        <input wire:model="ruleMin" type="number" placeholder="Optional"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max</label>
                        <input wire:model="ruleMax" type="number" placeholder="Optional"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Regex Pattern (optional)</label>
                        <input wire:model="ruleRegex" type="text" placeholder="e.g., /^[A-Z]/"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <div class="flex gap-3 sm:col-span-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            {{ $editingRuleId ? 'Update' : 'Create' }}
                        </button>
                        <button type="button" wire:click="$set('showRuleForm', false)" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rules</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($formRules as $rule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm text-gray-900 font-medium">{{ $rule->entity }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600 font-mono">{{ $rule->field }}</td>
                            <td class="px-6 py-3 text-sm text-gray-500">
                                @foreach ($rule->rules as $rKey => $rVal)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600 mr-1 mb-1">
                                        {{ $rKey }}{{ is_bool($rVal) ? '' : ': ' . $rVal }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rule->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button wire:click="openRuleForm({{ $rule->id }})" class="text-indigo-600 hover:text-indigo-800 text-sm mr-2">Edit</button>
                                <button wire:click="deleteFormRule({{ $rule->id }})" wire:confirm="Delete this form rule?" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No form rules yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $formRules->links() }}</div>
    @endif
</div>
