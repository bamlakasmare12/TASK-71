<?php

namespace Tests\Unit;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adversarial boundary tests for the reservation engine.
 * Focus: time-window spoofing, breach logic edge cases.
 */
class ReservationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private User $editor;
    private Service $service;
    private ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'Editor123!@#456',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Learner',
            'email' => 'learner@test.local',
            'password' => 'Learner123!@#456',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Boundary Test Service',
            'description' => 'For boundary testing',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $this->editor->id,
            'updated_by' => $this->editor->id,
        ]);

        $this->reservationService = app(ReservationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset time mock
        parent::tearDown();
    }

    // ── CHECK-IN WINDOW BOUNDARY TESTS ──

    /**
     * At exactly -16 minutes before start: check-in window is NOT open.
     * The window opens at -15 minutes.
     */
    public function test_checkin_rejected_at_minus_16_minutes(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        // Set time to 16 minutes before start
        Carbon::setTestNow($slotStart->copy()->subMinutes(16));

        $this->assertFalse($slot->isCheckInWindowOpen());
        $this->assertFalse($reservation->canCheckIn());

        $this->expectException(\DomainException::class);
        $this->reservationService->checkIn($reservation);
    }

    /**
     * At exactly -15 minutes before start: check-in window IS open.
     * This is the earliest valid check-in time.
     */
    public function test_checkin_accepted_at_minus_15_minutes(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        // Set time to exactly 15 minutes before start
        Carbon::setTestNow($slotStart->copy()->subMinutes(15));

        $this->assertTrue($slot->isCheckInWindowOpen());

        $result = $this->reservationService->checkIn($reservation);
        $this->assertEquals(ReservationStatus::CheckedIn, $result->status);
        $this->assertNotNull($result->checked_in_at);
    }

    /**
     * At exactly start time: check-in is accepted but NOT marked late
     * because isLateArrival checks now->isAfter(start), and now == start is not after.
     */
    public function test_checkin_at_exact_start_time_is_on_time(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        Carbon::setTestNow($slotStart->copy());

        $result = $this->reservationService->checkIn($reservation);
        $this->assertEquals(ReservationStatus::CheckedIn, $result->status);
    }

    /**
     * At +1 minute after start: check-in is late, marked as partial attendance.
     */
    public function test_checkin_at_plus_1_minute_is_partial(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        Carbon::setTestNow($slotStart->copy()->addMinute());

        $result = $this->reservationService->checkIn($reservation);
        $this->assertEquals(ReservationStatus::PartialAttendance, $result->status);
    }

    /**
     * At exactly +10 minutes after start: check-in window is still open (boundary).
     */
    public function test_checkin_accepted_at_plus_10_minutes(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        Carbon::setTestNow($slotStart->copy()->addMinutes(10));

        $this->assertTrue($slot->isCheckInWindowOpen());

        $result = $this->reservationService->checkIn($reservation);
        $this->assertEquals(ReservationStatus::PartialAttendance, $result->status);
    }

    /**
     * At +11 minutes after start: check-in window is CLOSED.
     * Must reject check-in attempt.
     */
    public function test_checkin_rejected_at_plus_11_minutes(): void
    {
        $slotStart = Carbon::parse('2026-04-10 10:00:00');
        $slot = $this->createSlot($slotStart);
        $reservation = $this->createConfirmedReservation($slot);

        Carbon::setTestNow($slotStart->copy()->addMinutes(11));

        $this->assertFalse($slot->isCheckInWindowOpen());
        $this->assertFalse($reservation->canCheckIn());

        $this->expectException(\DomainException::class);
        $this->reservationService->checkIn($reservation);
    }

    // ── BREACH / FREEZE BOUNDARY TESTS ──

    /**
     * Exactly 2 no-show breaches within 60 days MUST trigger a 7-day freeze.
     */
    public function test_two_breaches_within_60_days_triggers_freeze(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 00:00:00'));

        // Create 2 no-show penalties within the 60-day window
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'First no-show',
            'created_at' => Carbon::parse('2026-03-15 10:00:00'), // 26 days ago
        ]);
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Second no-show',
            'created_at' => Carbon::parse('2026-04-05 10:00:00'), // 5 days ago
        ]);

        // Process no-shows (which calls evaluateFreezes internally)
        $this->reservationService->processNoShows();

        $this->learner->refresh();
        $this->assertTrue($this->learner->isBookingFrozen());
        $this->assertTrue($this->learner->booking_frozen_until->isAfter(now()));
    }

    /**
     * 2 breaches but one is at 61 days ago (outside the 60-day window).
     * This should NOT trigger a freeze because only 1 breach falls in the window.
     */
    public function test_two_breaches_one_at_61_days_does_not_trigger_freeze(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 00:00:00'));

        // First penalty: 61 days ago (outside 60-day window)
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Old no-show',
            'created_at' => Carbon::parse('2026-02-08 10:00:00'), // 61 days ago
        ]);

        // Second penalty: 5 days ago (inside window)
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Recent no-show',
            'created_at' => Carbon::parse('2026-04-05 10:00:00'),
        ]);

        $this->reservationService->processNoShows();

        $this->learner->refresh();
        $this->assertFalse($this->learner->isBookingFrozen());
        $this->assertNull($this->learner->booking_frozen_until);
    }

    /**
     * Exactly 2 breaches at the boundary: one at day 60 (inclusive) and one recent.
     * Day 60 should be INSIDE the window.
     */
    public function test_breach_at_exactly_60_days_is_inside_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 00:00:00'));

        // First penalty: exactly 60 days ago
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Boundary no-show',
            'created_at' => Carbon::parse('2026-02-09 00:00:00'), // 60 days ago
        ]);

        // Second penalty: recent
        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Recent no-show',
            'created_at' => Carbon::parse('2026-04-09 10:00:00'),
        ]);

        $this->reservationService->processNoShows();

        $this->learner->refresh();
        $this->assertTrue($this->learner->isBookingFrozen());
    }

    /**
     * A single no-show should NOT trigger freeze regardless of severity.
     */
    public function test_single_breach_does_not_trigger_freeze(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 00:00:00'));

        Penalty::create([
            'user_id' => $this->learner->id,
            'type' => 'no_show',
            'reason' => 'Single no-show',
            'created_at' => Carbon::parse('2026-04-05 10:00:00'),
        ]);

        $this->reservationService->processNoShows();

        $this->learner->refresh();
        $this->assertFalse($this->learner->isBookingFrozen());
    }

    // ── HELPERS ──

    private function createSlot(Carbon $startTime): TimeSlot
    {
        return TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHour(),
            'capacity' => 5,
            'is_active' => true,
            'created_by' => $this->editor->id,
        ]);
    }

    private function createConfirmedReservation(TimeSlot $slot): Reservation
    {
        return Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slot->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subHour(),
        ]);
    }
}
