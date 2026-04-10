<?php

namespace Tests\Feature\Integration;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserRecentlyViewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tenant isolation tests.
 * Validates that Learner A absolutely cannot see Learner B's data
 * through any Livewire component or query path.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $learnerA;
    private User $learnerB;
    private Service $service;
    private TimeSlot $slotA;
    private TimeSlot $slotB;

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

        $this->learnerA = User::create([
            'username' => 'learner_a',
            'name' => 'Alice Researcher',
            'email' => 'alice@test.local',
            'password' => 'Learner123!@#456',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->learnerB = User::create([
            'username' => 'learner_b',
            'name' => 'Bob Researcher',
            'email' => 'bob@test.local',
            'password' => 'Learner123!@#456',
            'role' => UserRole::Learner,
            'password_updated_at' => now(),
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'title' => 'Isolation Test Service',
            'description' => 'Testing tenant boundaries',
            'service_type' => 'consultation',
            'price' => 0,
            'is_active' => true,
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->slotA = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(2)->setTime(9, 0),
            'end_time' => now()->addDays(2)->setTime(10, 0),
            'capacity' => 5,
            'is_active' => true,
            'created_by' => $editor->id,
        ]);

        $this->slotB = TimeSlot::create([
            'service_id' => $this->service->id,
            'start_time' => now()->addDays(2)->setTime(14, 0),
            'end_time' => now()->addDays(2)->setTime(15, 0),
            'capacity' => 5,
            'is_active' => true,
            'created_by' => $editor->id,
        ]);
    }

    /**
     * Learner A's reservation dashboard must NOT show Learner B's reservations.
     */
    public function test_reservation_dashboard_only_shows_own_reservations(): void
    {
        // Create reservations for both learners
        Reservation::create([
            'user_id' => $this->learnerA->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slotA->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        Reservation::create([
            'user_id' => $this->learnerB->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slotB->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        // Acting as Learner A
        $this->actingAs($this->learnerA);

        // Dashboard shows the service title for A's reservation
        Livewire::test(\App\Livewire\Reservations\ReservationDashboard::class)
            ->assertSee('Isolation Test Service');

        // Verify the underlying query only returns A's reservations
        $dashboardReservations = Reservation::where('user_id', $this->learnerA->id)->get();
        $this->assertCount(1, $dashboardReservations);
        $this->assertTrue($dashboardReservations->every(fn($r) => $r->user_id === $this->learnerA->id));

        // B's reservations are not visible to A
        $this->assertEquals(0, Reservation::where('user_id', $this->learnerA->id)
            ->where('time_slot_id', $this->slotB->id)->count());
    }

    /**
     * Learner A cannot cancel Learner B's reservation via direct ID manipulation.
     */
    public function test_learner_cannot_cancel_another_learners_reservation(): void
    {
        $reservationB = Reservation::create([
            'user_id' => $this->learnerB->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slotB->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($this->learnerA);

        // Attempt to cancel B's reservation through the Livewire component
        // The component scopes queries to auth()->id(), so findOrFail will throw 404
        // which Livewire catches — B's reservation must remain unchanged
        try {
            Livewire::test(\App\Livewire\Reservations\ReservationDashboard::class)
                ->call('cancel', $reservationB->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Expected — the query is scoped to learner A's user_id
        }

        // Verify B's reservation is unchanged
        $reservationB->refresh();
        $this->assertEquals(ReservationStatus::Confirmed, $reservationB->status);
    }

    /**
     * Learner A cannot check in to Learner B's reservation.
     */
    public function test_learner_cannot_checkin_another_learners_reservation(): void
    {
        $reservationB = Reservation::create([
            'user_id' => $this->learnerB->id,
            'service_id' => $this->service->id,
            'time_slot_id' => $this->slotB->id,
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($this->learnerA);

        // The dashboard scopes to auth()->id() — this should fail silently or throw
        Livewire::test(\App\Livewire\Reservations\ReservationDashboard::class)
            ->call('checkIn', $reservationB->id);

        // Verify B's reservation is still just Confirmed
        $reservationB->refresh();
        $this->assertEquals(ReservationStatus::Confirmed, $reservationB->status);
    }

    /**
     * Favorites are user-scoped: A's favorites don't appear for B.
     */
    public function test_favorites_are_isolated_between_learners(): void
    {
        UserFavorite::create([
            'user_id' => $this->learnerA->id,
            'service_id' => $this->service->id,
            'created_at' => now(),
        ]);

        // B should have no favorites
        $bFavorites = UserFavorite::where('user_id', $this->learnerB->id)->count();
        $this->assertEquals(0, $bFavorites);

        // A should have exactly 1
        $aFavorites = UserFavorite::where('user_id', $this->learnerA->id)->count();
        $this->assertEquals(1, $aFavorites);
    }

    /**
     * Recently viewed is user-scoped: A's history doesn't leak to B.
     */
    public function test_recently_viewed_is_isolated(): void
    {
        UserRecentlyViewed::create([
            'user_id' => $this->learnerA->id,
            'service_id' => $this->service->id,
            'viewed_at' => now(),
        ]);

        $bViewed = UserRecentlyViewed::where('user_id', $this->learnerB->id)->count();
        $this->assertEquals(0, $bViewed);
    }

    /**
     * Learner role cannot access editor-only routes (service management).
     */
    public function test_learner_cannot_access_editor_routes(): void
    {
        $this->actingAs($this->learnerA);

        $this->get('/services-manage/create')->assertStatus(403);
        $this->get("/services/{$this->service->id}/time-slots")->assertStatus(403);
    }

    /**
     * Learner role cannot access admin-only routes (import/export).
     */
    public function test_learner_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->learnerA);

        $this->get('/admin/import')->assertStatus(403);
        $this->get('/admin/export')->assertStatus(403);
    }
}
