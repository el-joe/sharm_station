<?php

namespace App\Repositories\Contracts;

use App\DTOs\Package\PackageFilterData;
use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Package>
 */
interface PackageRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @return Collection<int, Package>
     */
    public function featured(int $limit = 6): Collection;

    public function paginatePublished(PackageFilterData $filters): LengthAwarePaginator;

    public function findPublishedBySlug(string $slug): ?Package;

    /**
     * @return Builder<Package>
     */
    public function adminQuery(?string $status = null): Builder;

    public function findForEditor(int $id): Package;

    /**
     * Published packages matching every token on title or destination name.
     *
     * @param  list<string>  $tokens
     * @return Collection<int, Package>
     */
    public function suggest(array $tokens, int $limit): Collection;
}
