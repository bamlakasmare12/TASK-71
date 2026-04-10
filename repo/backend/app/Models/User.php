<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'phone_encrypted',
        'external_id_encrypted',
        'password_updated_at',
        'booking_frozen_until',
        'failed_login_attempts',
        'locked_until',
        'device_fingerprint',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'phone_encrypted',
        'external_id_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'phone_encrypted' => 'encrypted',
            'external_id_encrypted' => 'encrypted',
            'password_updated_at' => 'datetime',
            'booking_frozen_until' => 'datetime',
            'locked_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class)->orderByDesc('created_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->orderByDesc('created_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'user_favorites')
            ->withPivot('created_at');
    }

    public function recentlyViewed(): HasMany
    {
        return $this->hasMany(UserRecentlyViewed::class)->orderByDesc('viewed_at');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isBookingFrozen(): bool
    {
        return $this->booking_frozen_until !== null && $this->booking_frozen_until->isFuture();
    }

    public function isPasswordExpired(): bool
    {
        if ($this->password_updated_at === null) {
            return true;
        }

        return $this->password_updated_at->diffInDays(now()) >= 90;
    }

    public function requiresCaptcha(): bool
    {
        return $this->failed_login_attempts >= 3;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isEditor(): bool
    {
        return $this->role === UserRole::Editor;
    }

    public function isLearner(): bool
    {
        return $this->role === UserRole::Learner;
    }
}
