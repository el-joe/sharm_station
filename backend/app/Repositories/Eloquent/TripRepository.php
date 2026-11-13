<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Trip\TripAdminFilterData;
use App\DTOs\Trip\TripFilterData;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\TripType;
use App\Models\Booking;
use App\Models\PackageStageTrip;
use App\Models\Trip;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Eloquent\Concerns\MatchesSearchTokens;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Trip>
 */
class TripRepository extends BaseRepository implements TripRepositoryInterface
{
    use MatchesSearchTokens;

    protected function model(): string
    {
        return Trip::class;
    }

    public function paginatePublished(TripFilterData $filters): LengthAwarePaginator
    {
        $query = $this->query()
            ->published()
            ->with('destination')
            ->when($filters->types !== [], fn (Builder $query) => $query->whereIn('type', $filters->types))
            ->when($filters->destination, fn (Builder $query, string $slug) => $query->whereHas('destination', fn (Builder $destination) => $destination->where('slug', $slug)))
            ->when($filters->bookingType === BookingType::Private, fn (Builder $query) => $query->where('private_enabled', true))
            ->when($filters->bookingType === BookingType::Group, fn (Builder $query) => $query->where('group_enabled', true))
            ->when($filters->pricingMode, fn (Builder $query, $mode) => $query->where('pricing_mode', $mode))
            ->when($filters->priceMin !== null, fn (Builder $query) => $query->where('from_price', '>=', $filters->priceMin))
            ->when($filters->priceMax !== null, fn (Builder $query) => $query->where('from_price', '<=', $filters->priceMax))
            ->when($filters->freeCancellation, fn (Builder $query) => $query->whereNotNull('free_cancellation_hours'))
            ->when($filters->category, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title->en', 'like', "%{$search}%")->orWhere('title->it', 'like', "%{$search}%");
            }));

        match ($filters->sort) {
            'price_asc' => $query->orderBy('from_price'),
            'price_desc' => $query->orderByDesc('from_price'),
            'newest' => $query->latest('id'),
            'duration' => $query->orderBy('days')->orderBy('duration_hours'),
            'most_booked' => $query->orderByDesc(Booking::query()
                ->selectRaw('count(*)')
                ->whereColumn('bookings.bookable_id', 'trips.id')
                ->where('bookings.bookable_type', (new Trip)->getMorphClass())
                ->whereNotIn('bookings.status', BookingStatus::voided()))->orderByDesc('featured')->orderBy('sort'),
            default => $query->orderByDesc('featured')->orderBy('sort')->orderByDesc('id'),
        };

        return $query->paginate($filters->perPage)->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?Trip
    {
        return $this->query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'destination',
                'unitOptions',
                'privateTiers',
                'availabilityRules',
                'tripDays.items',
                'media',
                'seasonPrices' => fn ($query) => $query->where('is_active', true),
                'childPolicy.bands',
                'addons' => fn ($query) => $query->where('is_active', true),
            ])
            ->first();
    }

    public function featured(array $types, int $limit = 6): Collection
    {
        return $this->query()
            ->published()
            ->whereIn('type', $types)
            ->with('destination')
            ->orderByDesc('featured')
            ->orderBy('sort')
            ->limit($limit)
            ->get();
    }

    public function adminQuery(TripAdminFilterData $filters): Builder
    {
        return $this->query()
            ->select('trips.*')
            ->with('destination:id,name')
            ->withCount('unitOptions')
            ->when($filters->nile, fn (Builder $query) => $query->where('type', TripType::Nile), fn (Builder $query) => $query->where('type', '!=', TripType::Nile))
            ->when($filters->type, fn (Builder $query, TripType $type) => $query->where('type', $type))
            ->when($filters->destinationId, fn (Builder $query, int $id) => $query->where('destination_id', $id))
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->pricingMode, fn (Builder $query, $mode) => $query->where('pricing_mode', $mode));
    }

    public function findForEditor(int $id): Trip
    {
        return $this->query()
            ->with(['destination', 'unitOptions', 'privateTiers', 'availabilityRules', 'tripDays.items', 'addons'])
            ->findOrFail($id);
    }

    public function isUsedInPackages(Trip $trip): bool
    {
        return PackageStageTrip::query()->where('trip_id', $trip->id)->exists();
    }

    public function suggest(array $tokens, array $types, int $limit): Collection
    {
        $query = $this->query()->published()->with('destination')->whereIn('type', $types);
        $this->matchTokens($query, $tokens, ['title', 'destination.name']);

        return $query->orderByDesc('featured')->orderBy('sort')->limit($limit)->get();
    }
}
