<?php

namespace App\Services;

use App\Models\DataDictionary;
use Illuminate\Support\Facades\Cache;

class PenaltyConfigService
{
    private const CACHE_KEY = 'penalty_config';
    private const CACHE_TTL = 300; // 5 minutes

    private const DEFAULTS = [
        'mode' => 'fee',           // 'fee' or 'points'
        'cancel_fee' => 25.00,
        'cancel_points' => 50,
        'free_cancel_hours' => 24,
        'no_show_threshold' => 2,
        'no_show_window_days' => 60,
        'freeze_days' => 7,
        'expiry_minutes' => 30,
    ];

    private function loadConfig(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $entries = DataDictionary::where('type', 'penalty_config')
                ->where('is_active', true)
                ->get();

            $config = self::DEFAULTS;

            foreach ($entries as $entry) {
                $value = $entry->metadata['value'] ?? null;
                if ($value !== null && array_key_exists($entry->key, $config)) {
                    $config[$entry->key] = $value;
                }
            }

            return $config;
        });
    }

    public function getMode(): string
    {
        return (string) $this->loadConfig()['mode'];
    }

    public function getCancelFee(): float
    {
        return (float) $this->loadConfig()['cancel_fee'];
    }

    public function getCancelPoints(): int
    {
        return (int) $this->loadConfig()['cancel_points'];
    }

    public function getFreeCancelHours(): int
    {
        return (int) $this->loadConfig()['free_cancel_hours'];
    }

    public function getNoShowThreshold(): int
    {
        return (int) $this->loadConfig()['no_show_threshold'];
    }

    public function getNoShowWindowDays(): int
    {
        return (int) $this->loadConfig()['no_show_window_days'];
    }

    public function getFreezeDays(): int
    {
        return (int) $this->loadConfig()['freeze_days'];
    }

    public function getExpiryMinutes(): int
    {
        return (int) $this->loadConfig()['expiry_minutes'];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getPenaltyDescription(): string
    {
        $mode = $this->getMode();
        if ($mode === 'fee') {
            return sprintf('$%.2f late cancellation fee', $this->getCancelFee());
        }
        return sprintf('%d points deduction', $this->getCancelPoints());
    }
}
