<?php

namespace App\Repositories\Eloquent;

use App\Models\Destination;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Destination>
 */
class DestinationRepository extends BaseRepository implements DestinationRepositoryInterface
{
    protected function model(): string
    {
        return Destination::class;
    }

    public function active(): Collection
    {
        return $this->query()
            ->active()
            ->withCount(['trips' => fn (Builder $query) => $query->published()])
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Destination
    {
        return $this->query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->withCount(['trips' => fn (Builder $query) => $query->published()])
            ->with([
                'trips' => fn ($query) => $query->published()->with('destination')->orderByDesc('featured')->orderBy('sort'),
                'buildings' => fn ($query) => $query->published()->with('destination'),
            ])
            ->first();
    }

    public function adminQuery(): Builder
    {
        return $this->query()->select('destinations.*')->withCount(['trips', 'buildings']);
    }

    public function isInUse(Destination $destination): bool
    {
        return $destination->trips()->exists() || $destination->buildings()->exists();
    }
}
