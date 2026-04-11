<?php

namespace Tests\Api;

use App\Enums\AuditAction;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private Service $service;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $editor = User::create([
            'username' => 'editor',
            'name' => 'Editor',
            'email' => 'editor@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Editor,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->learner = User::create([
            'username' => 'learner',
            'name' => 'Learner',
            'email' => 'learner@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Audit Test Service',
            'description' => 'Service for audit log testing.',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->slot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'capacity' => 5,
            'booked_count' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
        ]);
    }

    public function test_reservation_expiry_creates_audit_log(): void
    {
        $reservation = Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slot->id,
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

        $this->slot->increment('booked_count');

        $service = app(ReservationService::class);
        $service->expireIfPending($reservation->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReservationExpired->value,
            'user_id' => $this->learner->id,
        ]);

        $reservation->refresh();
        $this->assertEquals(ReservationStatus::Cancelled, $reservation->status);
    }

    public function test_no_show_processing_creates_audit_logs(): void
    {
        $pastSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addMinutes(30),
            'capacity' => 5,
            'booked_count' => 1,
            'is_active' => true,
            'created_by' => $this->service->created_by,
        ]);

        Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $pastSlot->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subHour(),
        ]);

        $service = app(ReservationService::class);
        $processed = $service->processNoShows();

        $this->assertGreaterThanOrEqual(1, $processed);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReservationNoShow->value,
            'user_id' => $this->learner->id,
            'severity' => 'warning',
        ]);
    }

    public function test_checkout_creates_audit_log(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $response->json('data.id');
        $this->postJson("/api/reservations/{$reservationId}/confirm");

        $reservation = Reservation::find($reservationId);
        $reservation->update([
            'status' => ReservationStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $this->postJson("/api/reservations/{$reservationId}/check-out");

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReservationCheckedOut->value,
            'user_id' => $this->learner->id,
        ]);
    }

    public function test_reschedule_creates_audit_log(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $response->json('data.id');
        $this->postJson("/api/reservations/{$reservationId}/confirm");

        $newSlot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHour(),
            'capacity' => 5,
            'booked_count' => 0,
            'is_active' => true,
            'created_by' => $this->service->created_by,
        ]);

        $this->postJson("/api/reservations/{$reservationId}/reschedule", [
            'new_time_slot_id' => $newSlot->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReservationRescheduled->value,
            'user_id' => $this->learner->id,
        ]);
    }
}
