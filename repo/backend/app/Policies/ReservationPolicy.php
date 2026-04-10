<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All roles can view their own reservations
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->id === $reservation->user_id
            || $user->role === UserRole::Admin
            || $user->role === UserRole::Editor;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Learner && !$user->isBookingFrozen();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->id === $reservation->user_id && $reservation->canCancel();
    }

    public function checkIn(User $user, Reservation $reservation): bool
    {
        return $user->id === $reservation->user_id && $reservation->canCheckIn();
    }

    public function checkOut(User $user, Reservation $reservation): bool
    {
        if ($user->role === UserRole::Admin || $user->role === UserRole::Editor) {
            return true;
        }

        return $user->id === $reservation->user_id;
    }
}
