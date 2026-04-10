<?php

namespace App\Actions\Catalog;

use App\Models\User;
use App\Models\UserRecentlyViewed;

class RecordRecentView
{
    private const MAX_RECENT = 20;

    public function execute(User $user, int $serviceId): void
    {
        UserRecentlyViewed::updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $serviceId],
            ['viewed_at' => now()],
        );

        // Trim to MAX_RECENT entries
        $excess = UserRecentlyViewed::where('user_id', $user->id)
            ->orderByDesc('viewed_at')
            ->skip(self::MAX_RECENT)
            ->pluck('id');

        if ($excess->isNotEmpty()) {
            UserRecentlyViewed::whereIn('id', $excess)->delete();
        }
    }
}
