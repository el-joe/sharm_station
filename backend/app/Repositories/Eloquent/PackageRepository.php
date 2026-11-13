<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Package\PackageFilterData;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Eloquent\Concerns\MatchesSearchTokens;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Package>
 */
class PackageRepository extends BaseRepository implements PackageRepositoryInterface
{
    use MatchesSearchTokens;

    protected function model(): string
    {
        return Package::class;
    }

    public function featured(int $limit = 6): Collection
    {
        return $this->query()
            ->published()
            ->with(['destination', 'stages.destination'])
            ->orderByDesc('featured')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function paginatePublished(PackageFilterData $filters): LengthAwarePaginator
    {
        $query = $this->query()
            ->published()
            ->with(['destination', 'stages.destination'])
            ->when($filters->destination, fn (Builder $query, string $slug) => $query->where(fn (Builder $query) => $query
                ->whereHas('destination', fn (Builder $destination) => $destination->where('slug', $slug))
                ->orWhereHas('stages.destination', fn (Builder $destination) => $destination->where('slug', $slug))))
            ->when($filters->priceMin !== null, fn (Builder $query) => $query->whereRaw('COALESCE(from_price, base_price) >= ?', [$filters->priceMin]))
            ->when($filters->priceMax !== null, fn (Builder $query) => $query->whereRaw('COALESCE(from_price, base_price) <= ?', [$filters->priceMax]))
            ->when($filters->daysMin !== null, fn (Builder $query) => $query->where('days', '>=', $filters->daysMin))
            ->when($filters->daysMax !== null, fn (Builder $query) => $query->where('days', '<=', $filters->daysMax))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title->en', 'like', "%{$search}%")->orWhere('title->it', 'like', "%{$search}%");
            }));

        match ($filters->sort) {
            'price_asc' => $query->orderByRaw('COALESCE(from_price, base_price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(from_price, base_price) desc'),
            'duration' => $query->orderBy('days'),
            'newest' => $query->latest('id'),
            'most_booked' => $query->orderByDesc(Booking::query()
                ->selectRaw('count(*)')
                ->whereColumn('bookings.bookable_id', 'packages.id')
                ->where('bookings.bookable_type', (new Package)->getMorphClass())
                ->where('bookings.service_type', ServiceType::Package))->orderByDesc('featured'),
            default => $query->orderByDesc('featured')->orderBy('sort')->orderByDesc('id'),
        };

        return $query->paginate($filters->perPage)->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?Package
    {
        return $this->query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'destination',
                'stages.destination',
                'stages.stageTrips.trip.destination',
                'includedTransfers.stage',
                'seasonPrices' => fn ($query) => $query->where('is_active', true),
                'childPolicy.bands',
                'addons' => fn ($query) => $query->where('is_active', true),
            ])
            ->first();
    }

    public function adminQuery(?string $status = null): Builder
    {
        return $this->query()
            ->select('packages.*')
            ->with('destination:id,name')
            ->withCount(['stages', 'includedTransfers'])
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    public function findForEditor(int $id): Package
    {
        return $this->query()
            ->with(['destination', 'stages.stageTrips', 'includedTransfers', 'addons'])
            ->findOrFail($id);
    }

    public function suggest(array $tokens, int $limit): Collection
    {
        $query = $this->query()->published()->with('destination');
        $this->matchTokens($query, $tokens, ['title', 'destination.name']);

        return $query->orderByDesc('featured')->orderBy('sort')->limit($limit)->get();
    }
}
