<?php

namespace App\Services;

use App\DTOs\Nationality\NationalityGroupData;
use App\Exceptions\DomainValidationException;
use App\Models\Country;
use App\Models\NationalityGroup;
use App\Repositories\Contracts\NationalityGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of nationality groups and their countries (CR-10).
 */
class NationalityGroupService
{
    public function __construct(
        private readonly NationalityGroupRepositoryInterface $groups,
        private readonly NationalityService $nationalities,
    ) {}

    /**
     * @return Collection<int, NationalityGroup>
     */
    public function all(): Collection
    {
        return $this->groups->ordered();
    }

    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return $this->groups->countries();
    }

    public function create(NationalityGroupData $data): NationalityGroup
    {
        return DB::transaction(function () use ($data): NationalityGroup {
            $code = $data->code();
            $suffix = 1;

            while ($this->groups->query()->where('code', $code)->exists()) {
                $code = $data->code().'_'.(++$suffix);
            }

            /** @var NationalityGroup $group */
            $group = $this->groups->create([
                'code' => $code,
                'name' => $data->name,
                'sort' => $data->sort,
                'is_active' => $data->isActive,
                'is_default' => false,
            ]);

            return $this->applyAssignments($group, $data);
        });
    }

    /**
     * @throws DomainValidationException when the only default group would lose its default flag
     */
    public function update(NationalityGroup $group, NationalityGroupData $data): NationalityGroup
    {
        if ($group->is_default && ! $data->isDefault) {
            throw DomainValidationException::withErrors(['is_default' => ['Exactly one group must be the default. Make another group the default instead.']]);
        }

        return DB::transaction(function () use ($group, $data): NationalityGroup {
            $this->groups->update($group, ['name' => $data->name, 'sort' => $data->sort, 'is_active' => $data->isActive || $data->isDefault]);

            return $this->applyAssignments($group, $data);
        });
    }

    /**
     * @throws DomainValidationException when deleting the default group
     */
    public function delete(NationalityGroup $group): void
    {
        if ($group->is_default) {
            throw DomainValidationException::withErrors(['group' => ['The default group cannot be deleted. Make another group the default first.']]);
        }

        $this->groups->delete($group);
        $this->nationalities->flush();
    }

    private function applyAssignments(NationalityGroup $group, NationalityGroupData $data): NationalityGroup
    {
        $this->groups->syncCountries($group, $data->countries);

        if ($data->isDefault && ! $group->is_default) {
            $this->groups->makeDefault($group);
        }

        $this->nationalities->flush();

        return $group->refresh();
    }
}
