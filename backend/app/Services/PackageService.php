<?php

namespace App\Services;

use App\DTOs\Package\PackageData;
use App\DTOs\Package\PackageFilterData;
use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Packages (SRS 4.2): consecutive stages with trips per day, included transfers, add-ons and a price list.
 */
class PackageService
{
    public function __construct(
        private readonly PackageRepositoryInterface $packages,
        private readonly SeasonPriceService $seasons,
        private readonly CurrencyService $currencies,
    ) {}

    /**
     * Published packages; price bounds are given in the current currency.
     */
    public function paginate(PackageFilterData $filters): LengthAwarePaginator
    {
        return $this->packages->paginatePublished($filters->withPriceBounds(
            $filters->priceMin !== null ? $this->currencies->toEur($filters->priceMin) : null,
            $filters->priceMax !== null ? $this->currencies->toEur($filters->priceMax) : null,
        ));
    }

    public function findPublishedBySlug(string $slug): ?Package
    {
        return $this->packages->findPublishedBySlug($slug);
    }

    public function create(PackageData $data): Package
    {
        return DB::transaction(function () use ($data): Package {
            $package = $this->packages->create($data->attributes);
            $this->syncRelations($package, $data);

            return $package;
        });
    }

    public function update(Package $package, PackageData $data): Package
    {
        return DB::transaction(function () use ($package, $data): Package {
            $package = $this->packages->update($package, $data->attributes);
            $this->syncRelations($package, $data);

            return $package;
        });
    }

    public function delete(Package $package): void
    {
        $this->packages->delete($package);
    }

    /**
     * @return Builder<Package>
     */
    public function adminQuery(?string $status = null): Builder
    {
        return $this->packages->adminQuery($status);
    }

    public function findForEditor(int $id): Package
    {
        return $this->packages->findForEditor($id);
    }

    /**
     * Stages are rewritten in order; included transfers are linked to the stage that contains their day.
     */
    private function syncRelations(Package $package, PackageData $data): void
    {
        $package->stages()->delete();

        foreach ($data->stages as $position => $stageData) {
            $stage = $package->stages()->create([
                'destination_id' => $stageData->destinationId,
                'position' => $position + 1,
                'days' => $stageData->days,
                'nights' => $stageData->nights,
                'accommodation' => $stageData->accommodation === [] ? null : $stageData->accommodation,
            ]);

            foreach ($stageData->trips as $sort => $trip) {
                $stage->stageTrips()->create(['trip_id' => $trip['trip_id'], 'day_number' => $trip['day_number'], 'sort' => $sort]);
            }
        }

        $package->unsetRelation('stages');
        $startDays = $package->load('stages')->stageStartDays();

        $package->includedTransfers()->delete();

        foreach ($data->transfers as $transfer) {
            $stageId = null;

            foreach ($startDays as $id => $startDay) {
                if ($transfer->dayNumber >= $startDay) {
                    $stageId = $id;
                }
            }

            $package->includedTransfers()->create([...$transfer->toAttributes(), 'package_stage_id' => $stageId]);
        }

        $pivot = [];

        foreach (array_values(array_unique($data->addonIds)) as $position => $addonId) {
            $pivot[$addonId] = ['sort' => $position];
        }

        $package->addons()->sync($pivot);
        $package->unsetRelation('includedTransfers');

        $this->seasons->refreshFromPrice($package);
    }
}
