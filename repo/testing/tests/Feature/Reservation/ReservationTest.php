<?php

namespace Tests\Feature\Reservation;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private User $editor;
    private Service $service;
    private TimeSlot $slot;
    private ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Test Learner',
            'email' => 'learner@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->editor = User::create([
            'username' => 'editor',
            'name' => 'Test Editor',
            'email' => 'editor@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Test Service',
            'description' => 'A test service.',
            'service_type' => 'consultation',
            'target_audience' => ['faculty'],
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ]);

        $this->slot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(2)->setTime(10, 0),
            'end_time' => now()->addDays(2)->setTime(11, 0),
            'capacity' => 2,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);

        $this->reservationService = app(ReservationService::class);
    }

    public function test_create_reservation(): void
    {
        $reservation = $this->reservationService->createReservation($this->learner, $this->slot->id);

        $this->assertEquals(ReservationStatus::Pending, $reservation->status);
        $this->assertNotNull($reservation->expires_at);
        $this->assertEquals(1, $this->slot->fresh()->booked_count);
    }

    public function test_confirm_reservation(): void
    {
        $reservation = $this->reservationService->createReservation($this->learner, $this->slot->id);
        $confirmed = $this->reservationService->confirm($reservation);

        $this->assertEquals(ReservationStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNull($confirmed->expires_at);
    }

    public function test_cancel_reservation_free_before_24h(): void
    {
        $reservation = $this->reservationService->createReservation($this->learner, $this->slot->id);
        $this->reservationService->confirm($reservation);

        $cancelled = $this->reservationService->cancel($reservation->fresh(), 'Changed plans');

        $this->assertEquals(ReservationStatus::Cancelled, $cancelled->status);
        $this->assertEquals(0, $this->slot->fresh()->booked_count);
        $this->assertEquals(0, Penalty::where('reservation_id', $reservation->id)->count());
    }

    public function test_cancel_reservation_with_penalty_within_24h(): void
    {
        // Create a slot starting in 12 hours
        $nearSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addHours(12),
            'end_time' => now()->addHours(13),
            'capacity' => 2,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);

        $reservation = $this->reservationService->createReservation($this->learner, $nearSlot->id);
        $this->reservationService->confirm($reservation);
        $this->reservationService->cancel($reservation->fresh());

        $penalty = Penalty::where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($penalty);
        // PENALTY_MODE is 'fee' — only fee or points is applied, not both
        $this->assertEquals('25.00', $penalty->fee_amount);
        $this->assertEquals(0, $penalty->points_deducted);
    }

    public function test_frozen_user_cannot_book(): void
    {
        $this->learner->update(['booking_frozen_until' => now()->addDays(7)]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('frozen');

        $this->reservationService->createReservation($this->learner, $this->slot->id);
    }

    public function test_duplicate_booking_prevented(): void
    {
        $this->reservationService->createReservation($this->learner, $this->slot->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already have a reservation');

        $this->reservationService->createReservation($this->learner, $this->slot->id);
    }

    public function test_full_slot_rejected(): void
    {
        $fullSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(3)->setTime(10, 0),
            'end_time' => now()->addDays(3)->setTime(11, 0),
            'capacity' => 1,
            'booked_count' => 1,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no longer available');

        $this->reservationService->createReservation($this->learner, $fullSlot->id);
    }

    public function test_check_in_within_window(): void
    {
        // Create slot starting in 10 minutes (within 15-min pre window)
        $nearSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addMinutes(10),
            'end_time' => now()->addMinutes(70),
            'capacity' => 2,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);

        $reservation = $this->reservationService->createReservation($this->learner, $nearSlot->id);
        $this->reservationService->confirm($reservation);

        $checkedIn = $this->reservationService->checkIn($reservation->fresh());
        $this->assertEquals(ReservationStatus::CheckedIn, $checkedIn->status);
    }

    public function test_late_check_in_marked_partial(): void
    {
        // Create slot that started 5 minutes ago (within +10 min window but late)
        $pastSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addMinutes(55),
            'capacity' => 2,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);

        $reservation = Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $pastSlot->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subHour(),
        ]);

        $checkedIn = $this->reservationService->checkIn($reservation);
        $this->assertEquals(ReservationStatus::PartialAttendance, $checkedIn->status);
    }

    public function test_expire_pending_reservation(): void
    {
        $reservation = $this->reservationService->createReservation($this->learner, $this->slot->id);

        $this->reservationService->expireIfPending($reservation->id);

        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Cancelled, $reservation->status);
        $this->assertEquals(0, $this->slot->fresh()->booked_count);
    }

    public function test_no_show_processing_and_freeze(): void
    {
        // Create 2 no-show scenarios
        for ($i = 0; $i < 2; $i++) {
            $pastSlot = TimeSlot::create([
                'service_id' => $this->service->id,
                'start_time' => now()->subMinutes(20 + $i * 60),
                'end_time' => now()->subMinutes(20 + $i * 60 - 60),
                'capacity' => 1,
                'booked_count' => 1,
                'is_active' => true,
                'created_by' => $this->editor->id,
            ]);

            Reservation::create([
                'user_id' => $this->learner->id,
                'service_id' => $this->service->id,
                'time_slot_id' => $pastSlot->id,
                'status' => ReservationStatus::Confirmed,
                'confirmed_at' => now()->subHour(),
            ]);
        }

        $processed = $this->reservationService->processNoShows();

        $this->assertEquals(2, $processed);
        $this->learner->refresh();
        $this->assertTrue($this->learner->isBookingFrozen());
    }

    public function test_reservation_dashboard_requires_auth(): void
    {
        $response = $this->get('/reservations');
        $response->assertRedirect('/login');
    }

    public function test_reservation_dashboard_shows_user_reservations(): void
    {
        $reservation = $this->reservationService->createReservation($this->learner, $this->slot->id);
        $this->actingAs($this->learner);

        $response = $this->get('/reservations');
        $response->assertStatus(200);
    }
}
