<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\DataDictionaryService;
use App\Services\ReservationQueryService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private ReservationQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $reservations = $this->queryService->listForUser(
            $request->user()->id,
            $request->input('filter', 'all'),
        );

        return ReservationResource::collection($reservations);
    }

    public function store(Request $request, DataDictionaryService $dictService): ReservationResource|JsonResponse
    {
        $baseRules = ['time_slot_id' => 'required|integer|exists:time_slots,id'];
        $dynamicRules = $dictService->getValidationRules('reservation');
        $request->validate(array_merge($baseRules, $dynamicRules));

        $this->authorize('create', Reservation::class);

        try {
            $reservation = $this->reservationService->createReservation(
                $request->user(),
                $request->input('time_slot_id'),
            );
            return new ReservationResource($reservation->load(['service', 'timeSlot']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        $this->authorize('view', $reservation);

        return new ReservationResource($reservation->load(['service', 'timeSlot', 'penalties']));
    }

    public function confirm(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $reservation = $this->reservationService->confirm($reservation);
            return new ReservationResource($reservation->load(['service', 'timeSlot']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        $this->authorize('cancel', $reservation);

        try {
            $reservation = $this->reservationService->cancel($reservation, $request->input('reason'));
            return new ReservationResource($reservation->load(['service', 'timeSlot', 'penalties']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function checkIn(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        $this->authorize('checkIn', $reservation);

        try {
            $reservation = $this->reservationService->checkIn($reservation);
            return new ReservationResource($reservation->load(['service', 'timeSlot']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function checkOut(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        $this->authorize('checkOut', $reservation);

        try {
            $reservation = $this->reservationService->checkOut($reservation);
            return new ReservationResource($reservation->load(['service', 'timeSlot']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reschedule(Request $request, Reservation $reservation): ReservationResource|JsonResponse
    {
        $request->validate([
            'new_time_slot_id' => 'required|integer|exists:time_slots,id',
        ]);

        $this->authorize('cancel', $reservation);

        try {
            $newReservation = $this->reservationService->reschedule($reservation, $request->input('new_time_slot_id'));
            return new ReservationResource($newReservation->load(['service', 'timeSlot']));
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
