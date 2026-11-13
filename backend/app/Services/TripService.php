<?php

namespace App\Services;

use App\DTOs\Trip\AvailabilityRuleData;
use App\DTOs\Trip\PrivateTierData;
use App\DTOs\Trip\TripAdminFilterData;
use App\DTOs\Trip\TripData;
use App\DTOs\Trip\TripDayData;
use App\DTOs\Trip\TripFilterData;
use App\DTOs\Trip\UnitOptionData;
use App\Enums\TripType;
use App\Exceptions\DomainValidationException;
use App\Models\Trip;
use App\Repositories\Contracts\TripRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function __construct(
        private readonly TripRepositoryInterface $trips,
        private readonly SeasonPriceService $seasons,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * Published trips; price bounds are given in the current currency and compared to the EUR "from" price.
     */
    public function paginate(TripFilterData $filters): LengthAwarePaginator
    {
        $filters = $filters->withPriceBounds(
            $filters->priceMin !== null ? $this->currencies->toEur($filters->priceMin) : null,
            $filters->priceMax !== null ? $this->currencies->toEur($filters->priceMax) : null,
        );

        return $this->trips->paginatePublished($filters);
    }

    public function findPublishedBySlug(string $slug): ?Trip
    {
        return $this->trips->findPublishedBySlug($slug);
    }

    /**
     * @param  list<TripType>  $types
     * @return Collection<int, Trip>
     */
    public function featured(array $types, int $limit = 6): Collection
    {
        return $this->trips->featured($types, $limit);
    }

    public function create(TripData $data): Trip
    {
        return DB::transaction(function () use ($data): Trip {
            $trip = $this->trips->create($data->toAttributes());
            $this->syncRelations($trip, $data);

            return $trip;
        });
    }

    public function update(Trip $trip, TripData $data): Trip
    {
        return DB::transaction(function () use ($trip, $data): Trip {
            $trip = $this->trips->update($trip, $data->toAttributes());
            $this->syncRelations($trip, $data);

            return $trip;
        });
    }

    /**
     * @throws DomainValidationException when a package still uses the trip.
     */
    public function delete(Trip $trip): void
    {
        if ($this->trips->isUsedInPackages($trip)) {
            throw DomainValidationException::withErrors(['trip' => ['This trip is part of a package. Remove it from the package first.']]);
        }

        $this->trips->delete($trip);
    }

    /**
     * Updates options by id, creates new ones and deletes the ones not present.
     *
     * @param  list<UnitOptionData>  $options
     */
    public function syncUnitOptions(Trip $trip, array $options): void
    {
        $keep = [];

        foreach ($options as $option) {
            $model = $option->id !== null
                ? $trip->unitOptions()->whereKey($option->id)->first()
                : null;

            $model = $model !== null
                ? tap($model)->update($option->toAttributes())
                : $trip->unitOptions()->create($option->toAttributes());

            $keep[] = $model->id;
        }

        $trip->unitOptions()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  list<PrivateTierData>  $tiers
     */
    public function syncPrivateTiers(Trip $trip, array $tiers): void
    {
        $keep = [];

        foreach ($tiers as $tier) {
            $model = $tier->id !== null ? $trip->privateTiers()->whereKey($tier->id)->first() : null;

            $model = $model !== null
                ? tap($model)->update($tier->toAttributes())
                : $trip->privateTiers()->create($tier->toAttributes());

            $keep[] = $model->id;
        }

        $trip->privateTiers()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  list<AvailabilityRuleData>  $rules
     */
    public function syncAvailability(Trip $trip, array $rules): void
    {
        foreach ($rules as $rule) {
            $trip->availabilityRules()->updateOrCreate(['booking_type' => $rule->bookingType], $rule->toAttributes());
        }

        $trip->unsetRelation('availabilityRules');
    }

    /**
     * Replaces the itinerary: days are matched by day number, their timeline items are rewritten in order.
     *
     * @param  list<TripDayData>  $days
     */
    public function syncItinerary(Trip $trip, array $days): void
    {
        $keep = [];

        foreach ($days as $dayData) {
            $day = $trip->tripDays()->updateOrCreate(['day_number' => $dayData->dayNumber], $dayData->toAttributes());
            $day->items()->delete();

            foreach ($dayData->items as $item) {
                $day->items()->create($item->toAttributes());
            }

            $keep[] = $day->id;
        }

        $trip->tripDays()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  list<int>  $addonIds  in display order
     */
    public function syncAddons(Trip $trip, array $addonIds): void
    {
        $pivot = [];

        foreach (array_values(array_unique($addonIds)) as $position => $addonId) {
            $pivot[$addonId] = ['sort' => $position];
        }

        $trip->addons()->sync($pivot);
    }

    /**
     * Admin list query (DataTables).
     *
     * @return Builder<Trip>
     */
    public function adminQuery(TripAdminFilterData $filters): Builder
    {
        return $this->trips->adminQuery($filters);
    }

    /**
     * Trip with everything the editor needs.
     */
    public function findForEditor(int $id): Trip
    {
        return $this->trips->findForEditor($id);
    }

    private function syncRelations(Trip $trip, TripData $data): void
    {
        if ($data->availability !== null) {
            $this->syncAvailability($trip, $data->availability);
        }

        if ($data->days !== null) {
            $this->syncItinerary($trip, $data->days);
        }

        if ($data->addonIds !== null) {
            $this->syncAddons($trip, $data->addonIds);
        }

        $this->syncPricing($trip, $data);
    }

    private function syncPricing(Trip $trip, TripData $data): void
    {
        if ($data->unitOptions !== null) {
            $this->syncUnitOptions($trip, $data->unitOptions);
        }

        if ($data->privateTiers !== null) {
            $this->syncPrivateTiers($trip, $data->privateTiers);
        }

        $this->seasons->refreshFromPrice($trip);
    }
}
