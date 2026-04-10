<?php

namespace App\Livewire\Admin;

use App\Models\DataDictionary;
use App\Models\FormRule;
use App\Services\DictionaryQueryService;
use App\Services\InternalApiClient;
use Livewire\Component;
use Livewire\WithPagination;

class DictionaryManager extends Component
{
    use WithPagination;

    public string $activeTab = 'dictionaries'; // dictionaries, form_rules

    // Dictionary form
    public bool $showDictForm = false;
    public ?int $editingDictId = null;
    public string $dictType = '';
    public string $dictKey = '';
    public string $dictLabel = '';
    public int $dictSortOrder = 0;
    public bool $dictIsActive = true;

    // Form rule form
    public bool $showRuleForm = false;
    public ?int $editingRuleId = null;
    public string $ruleEntity = '';
    public string $ruleField = '';
    public bool $ruleRequired = false;
    public string $ruleType = 'string';
    public string $ruleMin = '';
    public string $ruleMax = '';
    public string $ruleRegex = '';
    public bool $ruleIsActive = true;

    public string $errorMessage = '';
    public string $successMessage = '';

    public function openDictForm(?int $id = null): void
    {
        $this->resetDictForm();
        if ($id) {
            $dict = DataDictionary::findOrFail($id);
            $this->editingDictId = $id;
            $this->dictType = $dict->type;
            $this->dictKey = $dict->key;
            $this->dictLabel = $dict->label;
            $this->dictSortOrder = $dict->sort_order ?? 0;
            $this->dictIsActive = $dict->is_active;
        }
        $this->showDictForm = true;
    }

    public function saveDictionary(InternalApiClient $api): void
    {
        $this->clearMessages();

        $this->validate([
            'dictType' => 'required|string|max:100',
            'dictKey' => 'required|string|max:100',
            'dictLabel' => 'required|string|max:255',
            'dictSortOrder' => 'integer|min:0',
        ]);

        $data = [
            'type' => $this->dictType,
            'key' => $this->dictKey,
            'label' => $this->dictLabel,
            'sort_order' => $this->dictSortOrder,
            'is_active' => $this->dictIsActive,
        ];

        if ($this->editingDictId) {
            $response = $api->put("admin/dictionaries/{$this->editingDictId}", $data);
            $action = 'updated';
        } else {
            $response = $api->post('admin/dictionaries', $data);
            $action = 'created';
        }

        if ($response['ok']) {
            $this->successMessage = "Dictionary entry {$action} successfully.";
            $this->showDictForm = false;
            $this->resetDictForm();
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? "Failed to {$action} dictionary entry.";
        }
    }

    public function deleteDictionary(int $id, InternalApiClient $api): void
    {
        $this->clearMessages();

        $response = $api->delete("admin/dictionaries/{$id}");

        if ($response['ok']) {
            $this->successMessage = 'Dictionary entry deleted.';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to delete dictionary entry.';
        }
    }

    public function openRuleForm(?int $id = null): void
    {
        $this->resetRuleForm();
        if ($id) {
            $rule = FormRule::findOrFail($id);
            $this->editingRuleId = $id;
            $this->ruleEntity = $rule->entity;
            $this->ruleField = $rule->field;
            $this->ruleRequired = !empty($rule->rules['required']);
            $this->ruleType = $rule->rules['type'] ?? 'string';
            $this->ruleMin = (string) ($rule->rules['min'] ?? '');
            $this->ruleMax = (string) ($rule->rules['max'] ?? '');
            $this->ruleRegex = $rule->rules['regex'] ?? '';
            $this->ruleIsActive = $rule->is_active;
        }
        $this->showRuleForm = true;
    }

    public function saveFormRule(InternalApiClient $api): void
    {
        $this->clearMessages();

        $this->validate([
            'ruleEntity' => 'required|string|max:100',
            'ruleField' => 'required|string|max:100',
            'ruleType' => 'required|string|in:string,integer,numeric,email,date,boolean',
        ]);

        $rules = ['type' => $this->ruleType];
        if ($this->ruleRequired) {
            $rules['required'] = true;
        }
        if ($this->ruleMin !== '') {
            $rules['min'] = (int) $this->ruleMin;
        }
        if ($this->ruleMax !== '') {
            $rules['max'] = (int) $this->ruleMax;
        }
        if ($this->ruleRegex !== '') {
            $rules['regex'] = $this->ruleRegex;
        }

        $data = [
            'entity' => $this->ruleEntity,
            'field' => $this->ruleField,
            'rules' => $rules,
            'is_active' => $this->ruleIsActive,
        ];

        if ($this->editingRuleId) {
            $response = $api->put("admin/form-rules/{$this->editingRuleId}", $data);
            $action = 'updated';
        } else {
            $response = $api->post('admin/form-rules', $data);
            $action = 'created';
        }

        if ($response['ok']) {
            $this->successMessage = "Form rule {$action} successfully.";
            $this->showRuleForm = false;
            $this->resetRuleForm();
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? "Failed to {$action} form rule.";
        }
    }

    public function deleteFormRule(int $id, InternalApiClient $api): void
    {
        $this->clearMessages();

        $response = $api->delete("admin/form-rules/{$id}");

        if ($response['ok']) {
            $this->successMessage = 'Form rule deleted.';
        } else {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Failed to delete form rule.';
        }
    }

    private function resetDictForm(): void
    {
        $this->editingDictId = null;
        $this->dictType = '';
        $this->dictKey = '';
        $this->dictLabel = '';
        $this->dictSortOrder = 0;
        $this->dictIsActive = true;
    }

    private function resetRuleForm(): void
    {
        $this->editingRuleId = null;
        $this->ruleEntity = '';
        $this->ruleField = '';
        $this->ruleRequired = false;
        $this->ruleType = 'string';
        $this->ruleMin = '';
        $this->ruleMax = '';
        $this->ruleRegex = '';
        $this->ruleIsActive = true;
    }

    private function clearMessages(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function render(DictionaryQueryService $queryService)
    {
        return view('livewire.admin.dictionary-manager', [
            'dictionaries' => $queryService->listDictionaries(),
            'formRules' => $queryService->listFormRules(),
        ])->layout('components.layouts.app', ['title' => 'Dictionary & Form Rules']);
    }
}
