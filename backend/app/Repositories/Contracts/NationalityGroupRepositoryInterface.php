<?php

namespace App\Repositories\Contracts;

use App\Models\Country;
use App\Models\NationalityGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<NationalityGroup>
 */
interface NationalityGroupRepositoryInterface extends BaseRepositoryInterface
{
    public function defaultGroup(): ?NationalityGroup;

    /**
     * Groups in display order, with the number of assigned countries.
     *
     * @return Collection<int, NationalityGroup>
     */
    public function ordered(): Collection;

    public function findCountry(string $code): ?Country;

    /**
     * Every country ordered by name, with its group.
     *
     * @return Collection<int, Country>
     */
    public function countries(): Collection;

    /**
     * Assigns exactly $codes to the group: listed countries move here, the others leave it.
     *
     * @param  list<string>  $codes
     */
    public function syncCountries(NationalityGroup $group, array $codes): void;

    public function makeDefault(NationalityGroup $group): void;
}
