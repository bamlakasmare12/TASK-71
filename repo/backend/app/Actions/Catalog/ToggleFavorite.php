<?php

namespace App\Actions\Catalog;

use App\Models\User;
use App\Models\UserFavorite;

class ToggleFavorite
{
    public function execute(User $user, int $serviceId): bool
    {
        $existing = UserFavorite::where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->first();

        if ($existing) {
            $existing->delete();
            return false; // unfavorited
        }

        UserFavorite::create([
            'user_id' => $user->id,
            'service_id' => $serviceId,
            'created_at' => now(),
        ]);

        return true; // favorited
    }
}
