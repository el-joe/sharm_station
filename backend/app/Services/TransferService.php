<?php

namespace App\Services;

use App\DTOs\Pricing\QuoteResultData;
use App\DTOs\Transfer\TransferQuoteRequestData;
use App\Enums\TransferStatus;
use App\Models\Booking;
use App\Models\TransferFixedRoute;
use App\Models\TransferHub;
use App\Models\TransferRequest;
use App\Models\VehicleType;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Contracts\TransferRequestRepositoryInterface;
use App\Repositories\Contracts\VehicleTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Transfers (SRS 5): catalogue of hubs, vehicles and fixed routes, and the requests created by bookings.
 */
class TransferService
{
    public function __construct(
        private readonly TransferHubRepositoryInterface $hubs,
        private readonly VehicleTypeRepositoryInterface $vehicles,
        private readonly TransferFixedRouteRepositoryInterface $fixedRoutes,
        private readonly TransferRequestRepositoryInterface $requests,
    ) {}

    /**
     * @return Collection<int, TransferHub>
     */
    public function enabledHubs(): Collection
    {
        return $this->hubs->enabled();
    }

    /**
     * @return Collection<int, VehicleType>
     */
    public function enabledVehicles(): Collection
    {
        return $this->vehicles->enabled();
    }

    /**
     * Stores the request with the distance and breakdown of the quote that was booked.
     */
    public function createRequest(Booking $booking, TransferQuoteRequestData $data, QuoteResultData $quote): TransferRequest
    {
        return $this->requests->create([
            'booking_id' => $booking->id,
            'transfer_hub_id' => $data->hubId,
            'vehicle_type_id' => $data->vehicleTypeId,
            'transfer_fixed_route_id' => $quote->context['fixed_route_id'] ?? null,
            'direction' => $data->direction,
            'destination_name' => $data->destinationName,
            'destination_lat' => $data->destinationLat,
            'destination_lng' => $data->destinationLng,
            'pickup_at' => $data->pickupAt,
            'return_at' => $data->returnAt,
            'round_trip' => $data->roundTrip,
            'passengers' => $data->passengers,
            'luggage' => $data->luggage,
            'extras' => $data->extras,
            'flight_number' => $data->flightNumber,
            'distance_km' => $quote->context['distance_km'] ?? 0,
            'duration_minutes' => $quote->context['duration_minutes'] ?? 0,
            'distance_provider' => $quote->context['distance_provider'] ?? 'unknown',
            'needs_review' => (bool) ($quote->context['distance_estimated'] ?? false),
            'breakdown' => $quote->context['legs'] ?? [],
            'price' => $quote->total,
            'status' => TransferStatus::AwaitingPayment,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  status, driver_name, driver_phone, car_type, car_plate, admin_notes
     */
    public function updateRequest(TransferRequest $request, array $attributes): TransferRequest
    {
        return $this->requests->update($request, $attributes);
    }

    /**
     * Keeps the operational status in step with the booking payment (paid -> ready to arrange).
     */
    public function syncWithPayment(Booking $booking, TransferStatus $status): void
    {
        $request = $booking->transferRequest;

        if ($request !== null && in_array($request->status, [TransferStatus::AwaitingPayment, TransferStatus::AwaitingReceiptReview], true)) {
            $this->requests->update($request, ['status' => $status]);
        }
    }

    /**
     * @return Builder<TransferRequest>
     */
    public function requestsQuery(?string $status = null): Builder
    {
        return $this->requests->adminQuery($status);
    }

    /**
     * @return Builder<TransferFixedRoute>
     */
    public function fixedRoutesQuery(): Builder
    {
        return $this->fixedRoutes->adminQuery();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveHub(?TransferHub $hub, array $attributes): TransferHub
    {
        return $hub === null ? $this->hubs->create($attributes) : $this->hubs->update($hub, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveVehicle(?VehicleType $vehicle, array $attributes): VehicleType
    {
        return $vehicle === null ? $this->vehicles->create($attributes) : $this->vehicles->update($vehicle, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveFixedRoute(?TransferFixedRoute $route, array $attributes): TransferFixedRoute
    {
        return $route === null ? $this->fixedRoutes->create($attributes) : $this->fixedRoutes->update($route, $attributes);
    }

    public function deleteHub(TransferHub $hub): void
    {
        $this->hubs->delete($hub);
    }

    public function deleteVehicle(VehicleType $vehicle): void
    {
        $this->vehicles->delete($vehicle);
    }

    public function deleteFixedRoute(TransferFixedRoute $route): void
    {
        $this->fixedRoutes->delete($route);
    }

    /**
     * @return Builder<TransferHub>
     */
    public function hubsQuery(): Builder
    {
        return $this->hubs->query()->select('transfer_hubs.*')->withCount('fixedRoutes');
    }

    /**
     * @return Builder<VehicleType>
     */
    public function vehiclesQuery(): Builder
    {
        return $this->vehicles->query()->select('vehicle_types.*');
    }

    /**
     * @return array<int, string>
     */
    public function hubOptions(): array
    {
        return $this->hubs->selectOptions();
    }

    /**
     * @return array<int, string>
     */
    public function vehicleOptions(): array
    {
        return $this->vehicles->selectOptions();
    }
}
