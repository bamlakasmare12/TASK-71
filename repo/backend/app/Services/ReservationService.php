<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ReservationStatus;
use App\Jobs\ExpireUnconfirmedReservation;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    private const EXPIRY_MINUTES = 30;
    private const FREE_CANCEL_HOURS = 24;
    private const CANCEL_FEE = 25.00;
    private const CANCEL_POINTS = 50;
    private const PENALTY_MODE = 'fee'; // 'fee' or 'points' - only one deduction path per penalty
    private const NO_SHOW_THRESHOLD = 2;
    private const NO_SHOW_WINDOW_DAYS = 60;
    private const FREEZE_DAYS = 7;

    public function __construct(private AuditService $audit) {}

    public function createReservation(User $user, int $timeSlotId): Reservation
    {
        if (!$user->isLearner()) {
            throw new \DomainException('Only learners can create reservations.');
        }

        if ($user->isBookingFrozen()) {
            throw new \DomainException('Your booking privileges are frozen until ' . $user->booking_frozen_until->format('M d, Y'));
        }

        return DB::transaction(function () use ($user, $timeSlotId) {
            // Pessimistic lock on the time slot
            $slot = TimeSlot::lockForUpdate()->findOrFail($timeSlotId);

            if (!$slot->hasAvailability()) {
                throw new \DomainException('This time slot is no longer available.');
            }

            if (!$slot->isFuture()) {
                throw new \DomainException('Cannot book a past time slot.');
            }

            // Check for existing reservation on same slot
            $existing = Reservation::where('user_id', $user->id)
                ->where('time_slot_id', $timeSlotId)
                ->whereNotIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ])
                ->exists();

            if ($existing) {
                throw new \DomainException('You already have a reservation for this time slot.');
            }

            // Remove any cancelled/no-show reservations for this user+slot
            // to allow rebooking (DB unique constraint on user_id+time_slot_id)
            Reservation::where('user_id', $user->id)
                ->where('time_slot_id', $timeSlotId)
                ->whereIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ])
                ->delete();

            $reservation = Reservation::create([
                'user_id' => $user->id,
                'service_id' => $slot->service_id,
                'time_slot_id' => $timeSlotId,
                'status' => ReservationStatus::Pending,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            ]);

            $slot->increment('booked_count');

            // Dispatch expiry job
            ExpireUnconfirmedReservation::dispatch($reservation->id)
                ->delay(now()->addMinutes(self::EXPIRY_MINUTES));

            $this->audit->log(AuditAction::ReservationCreated, $user->id, [
                'reservation_id' => $reservation->id,
                'time_slot_id' => $timeSlotId,
            ]);

            return $reservation;
        });
    }

    public function confirm(Reservation $reservation): Reservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw new \DomainException('Only pending reservations can be confirmed.');
        }

        if ($reservation->expires_at && $reservation->expires_at->isPast()) {
            throw new \DomainException('This reservation has expired.');
        }

        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);

        $this->audit->log(AuditAction::ReservationConfirmed, $reservation->user_id, [
            'reservation_id' => $reservation->id,
        ]);

        return $reservation;
    }

    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        if (!$reservation->canCancel()) {
            throw new \DomainException('This reservation cannot be cancelled.');
        }

        return DB::transaction(function () use ($reservation, $reason) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Release the slot
            $reservation->timeSlot->decrement('booked_count');

            // Apply late cancellation penalty (fee OR points, not both)
            if (!$reservation->isFreeCancellation()) {
                $feeAmount = self::PENALTY_MODE === 'fee' ? self::CANCEL_FEE : 0;
                $pointsDeducted = self::PENALTY_MODE === 'points' ? self::CANCEL_POINTS : 0;
                $reasonSuffix = self::PENALTY_MODE === 'fee'
                    ? sprintf('$%.2f fee applied.', self::CANCEL_FEE)
                    : sprintf('%d points deducted.', self::CANCEL_POINTS);

                Penalty::create([
                    'user_id' => $reservation->user_id,
                    'reservation_id' => $reservation->id,
                    'type' => 'late_cancellation',
                    'fee_amount' => $feeAmount,
                    'points_deducted' => $pointsDeducted,
                    'reason' => 'Cancelled less than 24 hours before start time. ' . $reasonSuffix,
                    'created_at' => now(),
                ]);
            }

            $this->audit->log(AuditAction::ReservationCancelled, $reservation->user_id, [
                'reservation_id' => $reservation->id,
                'free_cancellation' => $reservation->isFreeCancellation(),
            ]);

            return $reservation;
        });
    }

    public function reschedule(Reservation $reservation, int $newTimeSlotId): Reservation
    {
        if (!$reservation->canCancel()) {
            throw new \DomainException('This reservation cannot be rescheduled.');
        }

        return DB::transaction(function () use ($reservation, $newTimeSlotId) {
            // Cancel old reservation (no penalty for reschedule)
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Rescheduled',
            ]);
            $reservation->timeSlot->decrement('booked_count');

            $this->audit->log(AuditAction::ReservationRescheduled, $reservation->user_id, [
                'old_reservation_id' => $reservation->id,
                'old_time_slot_id' => $reservation->time_slot_id,
                'new_time_slot_id' => $newTimeSlotId,
            ]);

            // Create new reservation
            return $this->createReservation($reservation->user, $newTimeSlotId);
        });
    }

    public function checkIn(Reservation $reservation): Reservation
    {
        if (!$reservation->canCheckIn()) {
            throw new \DomainException('Check-in is not available at this time.');
        }

        $isLate = $reservation->timeSlot->isLateArrival();

        $reservation->update([
            'status' => $isLate
                ? ReservationStatus::PartialAttendance
                : ReservationStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $auditAction = $isLate
            ? AuditAction::ReservationPartialAttendance
            : AuditAction::ReservationCheckedIn;

        $this->audit->log($auditAction, $reservation->user_id, [
            'reservation_id' => $reservation->id,
            'late' => $isLate,
        ]);

        return $reservation;
    }

    public function checkOut(Reservation $reservation): Reservation
    {
        if (!in_array($reservation->status, [ReservationStatus::CheckedIn, ReservationStatus::PartialAttendance])) {
            throw new \DomainException('Cannot check out a reservation that is not checked in.');
        }

        $wasPartialAttendance = $reservation->status === ReservationStatus::PartialAttendance;

        // Partial attendance cannot extend into next slot
        if ($reservation->status === ReservationStatus::PartialAttendance) {
            $reservation->update([
                'checked_out_at' => min(now(), $reservation->timeSlot->end_time),
                'status' => ReservationStatus::Completed,
            ]);
        } else {
            $reservation->update([
                'checked_out_at' => now(),
                'status' => ReservationStatus::Completed,
            ]);
        }

        $this->audit->log(AuditAction::ReservationCheckedOut, $reservation->user_id, [
            'reservation_id' => $reservation->id,
            'partial_attendance' => $wasPartialAttendance,
        ]);

        return $reservation;
    }

    public function expireIfPending(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);

        if (!$reservation || $reservation->status !== ReservationStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Expired: not confirmed within 30 minutes.',
            ]);

            $reservation->timeSlot->decrement('booked_count');

            $this->audit->log(AuditAction::ReservationExpired, $reservation->user_id, [
                'reservation_id' => $reservation->id,
                'expired_after_minutes' => self::EXPIRY_MINUTES,
                'triggered_by' => 'system',
            ]);
        });
    }

    public function processNoShows(): int
    {
        $processed = 0;

        // Find all confirmed reservations where the check-in window has closed
        $noShows = Reservation::where('status', ReservationStatus::Confirmed)
            ->whereHas('timeSlot', function ($q) {
                $q->where('start_time', '<', now()->subMinutes(10));
            })
            ->get();

        foreach ($noShows as $reservation) {
            DB::transaction(function () use ($reservation) {
                $reservation->update(['status' => ReservationStatus::NoShow]);

                Penalty::create([
                    'user_id' => $reservation->user_id,
                    'reservation_id' => $reservation->id,
                    'type' => 'no_show',
                    'fee_amount' => 0,
                    'points_deducted' => 0,
                    'reason' => 'Did not check in within the allowed window.',
                    'created_at' => now(),
                ]);

                $this->audit->log(AuditAction::ReservationNoShow, $reservation->user_id, [
                    'reservation_id' => $reservation->id,
                    'time_slot_id' => $reservation->time_slot_id,
                    'triggered_by' => 'system',
                ], 'warning');
            });

            $processed++;
        }

        // Check breach thresholds and freeze accounts
        $this->evaluateFreezes();

        return $processed;
    }

    private function evaluateFreezes(): void
    {
        $windowStart = now()->subDays(self::NO_SHOW_WINDOW_DAYS);

        $breachCounts = Penalty::where('type', 'no_show')
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('user_id, count(*) as breach_count')
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [self::NO_SHOW_THRESHOLD])
            ->pluck('breach_count', 'user_id');

        foreach ($breachCounts as $userId => $count) {
            $user = User::find($userId);
            if ($user && !$user->isBookingFrozen()) {
                $user->update([
                    'booking_frozen_until' => now()->addDays(self::FREEZE_DAYS),
                ]);

                $this->audit->log(AuditAction::BookingFreeze, $userId, [
                    'breaches' => $count,
                    'frozen_until' => $user->booking_frozen_until->toIso8601String(),
                ], 'critical');
            }
        }
    }
}
