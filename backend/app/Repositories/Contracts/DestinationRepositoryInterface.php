<?php

namespace App\Repositories\Contracts;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Destination>
 */
interface DestinationRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, Destination>
     */
    public function active(): Collection;

    public function findActiveBySlug(string $slug): ?Destination;

    /**
     * @return Builder<Destination>
     */
    public function adminQuery(): Builder;

    public function isInUse(Destination $destination): bool;

    /**
     * Active destinations whose name matches every token (all active ones when no tokens).
     *
     * @param  list<string>  $tokens
     * @return Collection<int, Destination>
     */
    public function suggest(array $tokens, int $limit): Collection;
}
