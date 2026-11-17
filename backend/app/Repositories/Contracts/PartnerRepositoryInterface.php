<?php

namespace App\Repositories\Contracts;

use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<Partner>
 */
interface PartnerRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUserId(int $userId): ?Partner;

    public function countByStatus(PartnerStatus $status): int;

    /**
     * @return Builder<Partner>
     */
    public function adminQuery(?PartnerStatus $status = null): Builder;

    /**
     * Partners shown in the commission matrix (everyone but suspended), with their category commissions.
     *
     * @return Collection<int, Partner>
     */
    public function forCommissionMatrix(): Collection;

    /**
     * id => company, for filter selects.
     *
     * @return array<int, string>
     */
    public function options(): array;

    /**
     * Sets (or, with null, removes) a partner's commission override for one type.
     */
    public function setCategoryCommission(Partner $partner, CommissionCategory $category, ?float $percent): void;

    /**
     * Partners with at least one commission override, by company.
     *
     * @return Collection<int, Partner>
     */
    public function withCommissionOverrides(): Collection;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Partner>
     */
    public function findMany(array $ids): Collection;
}
