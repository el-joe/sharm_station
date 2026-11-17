<?php

namespace App\Repositories\Eloquent;

use App\Models\Country;
use App\Models\NationalityGroup;
use App\Repositories\Contracts\NationalityGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<NationalityGroup>
 */
class NationalityGroupRepository extends BaseRepository implements NationalityGroupRepositoryInterface
{
    protected function model(): string
    {
        return NationalityGroup::class;
    }

    public function defaultGroup(): ?NationalityGroup
    {
        return $this->query()->where('is_default', true)->first();
    }

    public function ordered(): Collection
    {
        return $this->query()->withCount('countries')->orderBy('sort')->orderBy('id')->get();
    }

    public function findCountry(string $code): ?Country
    {
        return Country::query()->find(strtoupper($code));
    }

    public function countries(): Collection
    {
        return Country::query()->with('nationalityGroup')->orderBy('sort')->get();
    }

    public function syncCountries(NationalityGroup $group, array $codes): void
    {
        $codes = array_values(array_unique(array_map('strtoupper', $codes)));

        Country::query()->where('nationality_group_id', $group->id)->whereNotIn('code', $codes)->update(['nationality_group_id' => null]);
        Country::query()->whereIn('code', $codes)->update(['nationality_group_id' => $group->id]);
    }

    public function makeDefault(NationalityGroup $group): void
    {
        $this->query()->whereKeyNot($group->id)->where('is_default', true)->update(['is_default' => false]);
        $group->forceFill(['is_default' => true])->save();
    }
}
