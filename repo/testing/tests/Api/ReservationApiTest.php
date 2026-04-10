<?php

namespace Tests\Api;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TimeSlot;
use Tests\Api\Concerns\CreatesTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    // ── POST /api/reservations (create booking) ──

    public function test_create_reservation_succeeds_for_learner(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $this->actingAs($learner)
            ->postJson('/api/reservations', ['time_slot_id' => $slot->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reservations', [
            'user_id' => $learner->id,
            'time_slot_id' => $slot->id,
            'status' => 'pending',
        ]);
    }

    public function test_create_reservation_returns_422_for_full_slot(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor, ['capacity' => 1, 'booked_count' => 1]);

        $this->actingAs($this->createLearner())
            ->postJson('/api/reservations', ['time_slot_id' => $slot->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'This time slot is no longer available.');
    }

    public function test_create_reservation_returns_422_for_frozen_user(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner(['booking_frozen_until' => now()->addDays(7)]);

        $response = $this->actingAs($learner)
            ->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $this->assertContains($response->status(), [403, 422]);
    }

    public function test_create_reservation_forbidden_for_editor(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);

        $this->actingAs($editor)
            ->postJson('/api/reservations', ['time_slot_id' => $slot->id])
            ->assertForbidden();
    }

    public function test_create_reservation_prevents_double_booking(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);

        $this->actingAs($learner)
            ->postJson('/api/reservations', ['time_slot_id' => $slot->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'You already have a reservation for this time slot.');
    }

    public function test_create_reservation_validation(): void
    {
        $this->actingAs($this->createLearner())
            ->postJson('/api/reservations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['time_slot_id']);
    }

    // ── GET /api/reservations ──

    public function test_list_reservations_returns_own_only(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot1 = $this->createTimeSlot($service, $editor);
        $slot2 = $this->createTimeSlot($service, $editor, [
            'start_time' => now()->addDays(4)->setTime(10, 0),
            'end_time' => now()->addDays(4)->setTime(11, 0),
        ]);

        $learnerA = $this->createLearner();
        $learnerB = $this->createLearner();

        $this->actingAs($learnerA)->postJson('/api/reservations', ['time_slot_id' => $slot1->id]);
        $this->actingAs($learnerB)->postJson('/api/reservations', ['time_slot_id' => $slot2->id]);

        $response = $this->actingAs($learnerA)->getJson('/api/reservations');
        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('user_id')->unique();
        $this->assertCount(1, $userIds);
        $this->assertEquals($learnerA->id, $userIds->first());
    }

    // ── GET /api/reservations/{id} ──

    public function test_show_reservation_own(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $createResp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $reservationId = $createResp->json('data.id');

        $this->actingAs($learner)
            ->getJson("/api/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $reservationId);
    }

    public function test_show_reservation_forbidden_for_other_learner(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learnerA = $this->createLearner();
        $learnerB = $this->createLearner();

        $resp = $this->actingAs($learnerA)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');

        $this->actingAs($learnerB)
            ->getJson("/api/reservations/{$id}")
            ->assertForbidden();
    }

    // ── POST /api/reservations/{id}/confirm ──

    public function test_confirm_reservation(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');

        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_confirm_already_confirmed_fails(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');

        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");
        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/confirm")
            ->assertStatus(422);
    }

    // ── POST /api/reservations/{id}/cancel ──

    public function test_cancel_reservation_free(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');

        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/cancel", ['reason' => 'Changed plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(0, $slot->fresh()->booked_count);
    }

    public function test_cancel_within_24h_applies_penalty(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor, [
            'start_time' => now()->addHours(12),
            'end_time' => now()->addHours(13),
        ]);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");

        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('penalties', [
            'user_id' => $learner->id,
            'type' => 'late_cancellation',
        ]);
    }

    public function test_cancel_forbidden_for_other_user(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor);
        $learnerA = $this->createLearner();
        $learnerB = $this->createLearner();

        $resp = $this->actingAs($learnerA)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');

        $this->actingAs($learnerB)
            ->postJson("/api/reservations/{$id}/cancel")
            ->assertForbidden();
    }

    // ── POST /api/reservations/{id}/check-in ──

    public function test_checkin_within_window(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor, [
            'start_time' => now()->addMinutes(10),
            'end_time' => now()->addMinutes(70),
        ]);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");

        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
    }

    public function test_checkin_outside_window_fails(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor); // 3 days away
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");

        $response = $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/check-in");
        $this->assertContains($response->status(), [403, 422]);
    }

    // ── POST /api/reservations/{id}/check-out ──

    public function test_checkout_after_checkin(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot = $this->createTimeSlot($service, $editor, [
            'start_time' => now()->addMinutes(10),
            'end_time' => now()->addMinutes(70),
        ]);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot->id]);
        $id = $resp->json('data.id');
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/check-in");

        $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    // ── POST /api/reservations/{id}/reschedule ──

    public function test_reschedule_to_new_slot(): void
    {
        $editor = $this->createEditor();
        $service = $this->createService($editor);
        $slot1 = $this->createTimeSlot($service, $editor);
        $slot2 = $this->createTimeSlot($service, $editor, [
            'start_time' => now()->addDays(5)->setTime(14, 0),
            'end_time' => now()->addDays(5)->setTime(15, 0),
        ]);
        $learner = $this->createLearner();

        $resp = $this->actingAs($learner)->postJson('/api/reservations', ['time_slot_id' => $slot1->id]);
        $id = $resp->json('data.id');
        $this->actingAs($learner)->postJson("/api/reservations/{$id}/confirm");

        $response = $this->actingAs($learner)
            ->postJson("/api/reservations/{$id}/reschedule", ['new_time_slot_id' => $slot2->id]);
        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'pending');

        // Old reservation should be cancelled
        $this->assertEquals('cancelled', Reservation::find($id)->status->value);
    }

    // ── Auth guard ──

    public function test_all_reservation_endpoints_require_auth(): void
    {
        $this->getJson('/api/reservations')->assertUnauthorized();
        $this->postJson('/api/reservations', [])->assertUnauthorized();
        $this->getJson('/api/reservations/1')->assertUnauthorized();
        $this->postJson('/api/reservations/1/confirm')->assertUnauthorized();
        $this->postJson('/api/reservations/1/cancel')->assertUnauthorized();
        $this->postJson('/api/reservations/1/check-in')->assertUnauthorized();
        $this->postJson('/api/reservations/1/check-out')->assertUnauthorized();
        $this->postJson('/api/reservations/1/reschedule', [])->assertUnauthorized();
    }
}
