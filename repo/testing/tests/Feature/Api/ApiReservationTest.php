<?php

namespace Tests\Feature\Api;

use App\Enums\AuditAction;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;
    private User $otherLearner;
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

        $this->otherLearner = User::create([
            'username' => 'other_learner',
            'name' => 'Other Learner',
            'email' => 'other@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Test Service',
            'description' => 'A test consultation service.',
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

    public function test_api_create_reservation_requires_auth(): void
    {
        $response = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_api_create_reservation(): void
    {
        $this->actingAs($this->learner);

        $response = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', ReservationStatus::Pending->value);
    }

    public function test_api_confirm_reservation(): void
    {
        $this->actingAs($this->learner);
        $createResponse = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $createResponse->json('data.id');

        $response = $this->postJson("/api/reservations/{$reservationId}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Confirmed->value);
    }

    public function test_api_confirm_other_users_reservation_returns_403(): void
    {
        $reservation = Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slot->id,
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($this->otherLearner);

        $response = $this->postJson("/api/reservations/{$reservation->id}/confirm");

        $response->assertStatus(403);
    }

    public function test_api_cancel_reservation(): void
    {
        $this->actingAs($this->learner);
        $createResponse = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $createResponse->json('data.id');

        // Confirm first
        $this->postJson("/api/reservations/{$reservationId}/confirm");

        $response = $this->postJson("/api/reservations/{$reservationId}/cancel", [
            'reason' => 'Changed plans',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
    }

    public function test_api_show_reservation_returns_own(): void
    {
        $reservation = Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slot->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->learner);

        $response = $this->getJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $reservation->id);
    }

    public function test_api_show_reservation_returns_403_for_other_user(): void
    {
        $reservation = Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slot->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->otherLearner);

        $response = $this->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(403);
    }

    public function test_api_index_returns_only_own_reservations(): void
    {
        Reservation::create([
            'user_id' => $this->learner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slot->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $slot2 = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'capacity' => 5,
            'booked_count' => 0,
            'is_active' => true,
            'created_by' => $this->service->created_by,
        ]);

        Reservation::create([
            'user_id' => $this->otherLearner->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slot2->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->learner);

        $response = $this->getJson('/api/reservations');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $reservation) {
            $this->assertEquals($this->learner->id, $reservation['user_id']);
        }
    }

    public function test_api_reschedule_reservation(): void
    {
        $this->actingAs($this->learner);
        $createResponse = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $createResponse->json('data.id');

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

        $response = $this->postJson("/api/reservations/{$reservationId}/reschedule", [
            'new_time_slot_id' => $newSlot->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', ReservationStatus::Pending->value);

        // Verify audit log for reschedule
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ReservationRescheduled->value,
        ]);
    }

    public function test_api_reservation_lifecycle_produces_audit_logs(): void
    {
        $this->actingAs($this->learner);

        // Create
        $createResponse = $this->postJson('/api/reservations', [
            'time_slot_id' => $this->slot->id,
        ]);
        $reservationId = $createResponse->json('data.id');

        // Confirm
        $this->postJson("/api/reservations/{$reservationId}/confirm");

        // Cancel
        $this->postJson("/api/reservations/{$reservationId}/cancel");

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ReservationCreated->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ReservationConfirmed->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ReservationCancelled->value]);
    }
}
