<?php

namespace App\Repositories\Contracts;

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
}
