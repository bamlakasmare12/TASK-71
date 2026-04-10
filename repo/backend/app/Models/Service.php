<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'service_type',
        'eligibility_notes',
        'target_audience',
        'price',
        'is_free',
        'category',
        'project_number',
        'patent_number',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_audience' => 'array',
            'price' => 'decimal:2',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (empty($service->slug)) {
                $service->slug = Str::slug($service->title);
            }
            $service->is_free = $service->price <= 0;
        });

        static::updating(function (Service $service) {
            $service->is_free = $service->price <= 0;
        });
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'service_tag');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorites')
            ->withPivot('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'ilike', "%{$term}%")
              ->orWhere('description', 'ilike', "%{$term}%");
        });
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->whereJsonContains('target_audience', $audience);
    }

    public function nextAvailableSlot()
    {
        return $this->timeSlots()
            ->where('start_time', '>', now())
            ->where('is_active', true)
            ->whereColumn('booked_count', '<', 'capacity')
            ->orderBy('start_time')
            ->first();
    }
}
