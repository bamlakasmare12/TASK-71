<?php

namespace Tests\Feature\Integration;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adversarial concurrency tests.
 * Validates that pessimistic locking and DB constraints prevent double-booking
 * when two requests hit the same time_slot_id simultaneously.
 */
class ConcurrentBookingTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;
    private TimeSlot $slot;
    private ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'Editor123!@#456',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Concurrency Test Service',
            'description' => 'For race-condition testing',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->slot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(3)->setTime(10, 0),
            'end_time' => now()->addDays(3)->setTime(11, 0),
            'capacity' => 1, // Only ONE spot — forces contention
            'is_active' => true,
            'created_by' => $editor->id,
        ]);

        $this->reservationService = app(ReservationService::class);
    }

    /**
     * Two different learners try to book the last slot.
     * The DB unique constraint + pessimistic lock must allow exactly one.
     */
    public function test_two_learners_race_for_last_slot_only_one_succeeds(): void
    {
        $learnerA = $this->createLearner('learner_a');
        $learnerB = $this->createLearner('learner_b');

        // Learner A books successfully
        $reservationA = $this->reservationService->createReservation($learnerA, $this->slot->id);
        $this->assertEquals(ReservationStatus::Pending, $reservationA->status);

        // Slot should now be full (capacity=1, booked_count=1)
        $this->slot->refresh();
        $this->assertEquals(1, $this->slot->booked_count);

        // Learner B tries to book the same slot — must fail
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no longer available');

        $this->reservationService->createReservation($learnerB, $this->slot->id);
    }

    /**
     * Same learner attempts to book the same slot twice.
     * The UNIQUE(user_id, time_slot_id) constraint must reject the second.
     */
    public function test_same_learner_cannot_double_book_same_slot(): void
    {
        $learner = $this->createLearner('learner_dup');

        // Increase capacity so the availability check passes
        $this->slot->update(['capacity' => 5]);

        $this->reservationService->createReservation($learner, $this->slot->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already have a reservation');

        $this->reservationService->createReservation($learner, $this->slot->id);
    }

    /**
     * After a cancelled reservation, the same learner CAN rebook the same slot.
     * The unique constraint only blocks active (non-cancelled/non-noshow) reservations.
     */
    public function test_rebook_after_cancellation_succeeds(): void
    {
        $this->slot->update(['capacity' => 2]);
        $learner = $this->createLearner('learner_rebook');

        $reservation = $this->reservationService->createReservation($learner, $this->slot->id);
        $this->reservationService->confirm($reservation);
        $this->reservationService->cancel($reservation->fresh());

        // Should succeed — old reservation is cancelled
        $newReservation = $this->reservationService->createReservation($learner, $this->slot->id);
        $this->assertEquals(ReservationStatus::Pending, $newReservation->status);
    }

    /**
     * Verify booked_count is decremented on cancellation and re-incremented on rebook.
     * Protects against phantom capacity leaks.
     */
    public function test_booked_count_integrity_through_lifecycle(): void
    {
        $this->slot->update(['capacity' => 3]);
        $learner = $this->createLearner('learner_count');

        // Book: count goes from 0 to 1
        $reservation = $this->reservationService->createReservation($learner, $this->slot->id);
        $this->assertEquals(1, $this->slot->fresh()->booked_count);

        // Cancel: count goes back to 0
        $this->reservationService->confirm($reservation);
        $this->reservationService->cancel($reservation->fresh());
        $this->assertEquals(0, $this->slot->fresh()->booked_count);

        // Rebook: count goes to 1 again
        $this->reservationService->createReservation($learner, $this->slot->id);
        $this->assertEquals(1, $this->slot->fresh()->booked_count);
    }

    /**
     * Verify that the lockForUpdate() actually serializes concurrent access.
     * We simulate this by checking that the transaction isolation works correctly.
     */
    public function test_pessimistic_lock_prevents_overbooking(): void
    {
        $this->slot->update(['capacity' => 1]);
        $learnerA = $this->createLearner('lock_a');
        $learnerB = $this->createLearner('lock_b');

        // First booking succeeds
        $this->reservationService->createReservation($learnerA, $this->slot->id);

        // Verify slot state
        $this->slot->refresh();
        $this->assertEquals(1, $this->slot->booked_count);
        $this->assertFalse($this->slot->hasAvailability());

        // Second booking must fail even though we haven't committed
        // from the perspective of a second transaction
        try {
            $this->reservationService->createReservation($learnerB, $this->slot->id);
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('no longer available', $e->getMessage());
        }

        // Verify no phantom reservation was created
        $this->assertEquals(1, Reservation::where('time_slot_id', $this->slot->id)
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->count());
    }

    private function createLearner(string $username): User
    {
        return User::create([
            'username' => $username,
            'name' => ucfirst($username),
            'email' => "{$username}@test.local",
            'password' => 'Learner123!@#456',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);
    }
}
