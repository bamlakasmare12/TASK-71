<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Service $service;

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

        $this->userA = User::create([
            'username' => 'user_a',
            'name' => 'User A',
            'email' => 'a@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'username' => 'user_b',
            'name' => 'User B',
            'email' => 'b@test.local',
            'password' => 'TestPassword123!@#',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Shared Service',
            'description' => 'A service both users can book.',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_api_reservation_index_only_shows_own(): void
    {
        $slotA = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'capacity' => 5, 'booked_count' => 0, 'is_active' => true, 'created_by' => $this->service->created_by,
        ]);

        $slotB = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'capacity' => 5, 'booked_count' => 0, 'is_active' => true, 'created_by' => $this->service->created_by,
        ]);

        Reservation::create([
            'user_id' => $this->userA->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slotA->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        Reservation::create([
            'user_id' => $this->userB->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slotB->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        // User A sees only their reservation
        $this->actingAs($this->userA);
        $response = $this->getJson('/api/reservations');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->userA->id, $data[0]['user_id']);

        // User B sees only their reservation
        $this->actingAs($this->userB);
        $response = $this->getJson('/api/reservations');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->userB->id, $data[0]['user_id']);
    }

    public function test_api_reservation_show_returns_403_for_other_user(): void
    {
        $slot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'capacity' => 5, 'booked_count' => 0, 'is_active' => true, 'created_by' => $this->service->created_by,
        ]);

        $reservation = Reservation::create([
            'user_id' => $this->userA->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slot->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->userB);

        $response = $this->getJson("/api/reservations/{$reservation->id}");
        $response->assertStatus(403);
    }

    public function test_api_reservation_cancel_returns_403_for_other_user(): void
    {
        $slot = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'capacity' => 5, 'booked_count' => 0, 'is_active' => true, 'created_by' => $this->service->created_by,
        ]);

        $reservation = Reservation::create([
            'user_id' => $this->userA->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $slot->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->userB);

        $response = $this->postJson("/api/reservations/{$reservation->id}/cancel");
        $response->assertStatus(403);
    }

    public function test_api_favorites_are_isolated(): void
    {
        // User A favorites a service
        $this->actingAs($this->userA);
        $this->postJson("/api/catalog/{$this->service->id}/favorite");

        // User A sees their favorite
        $response = $this->getJson('/api/catalog/favorites');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));

        // User B sees no favorites
        $this->actingAs($this->userB);
        $response = $this->getJson('/api/catalog/favorites');
        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
