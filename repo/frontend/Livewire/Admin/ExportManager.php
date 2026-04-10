<?php

namespace App\Livewire\Admin;

use App\Services\InternalApiClient;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportManager extends Component
{
    public string $entity = 'services';
    public string $format = 'csv';
    public bool $includeSensitive = false;
    public bool $incrementalOnly = false;
    public string $sinceDate = '';
    public string $errorMessage = '';

    public function export(InternalApiClient $api): StreamedResponse|null
    {
        $this->errorMessage = '';

        $data = [
            'entity' => $this->entity,
            'format' => $this->format,
            'include_sensitive' => $this->includeSensitive,
        ];

        if ($this->incrementalOnly && $this->sinceDate) {
            $data['since'] = $this->sinceDate;
        }

        $response = $api->post('admin/export', $data);

        if (!$response['ok']) {
            $this->errorMessage = $response['error'] ?? $response['message'] ?? 'Export failed.';
            return null;
        }

        $exportData = $response['data'];
        $content = $exportData['content'] ?? '';
        $filename = $exportData['filename'] ?? $this->entity . '_export.' . $this->format;
        $mimeType = $exportData['mime_type'] ?? ($this->format === 'json' ? 'application/json' : 'text/csv');

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.export-manager')
            ->layout('components.layouts.app', ['title' => 'Export Data']);
    }
}
