<?php

namespace App\Services;

use App\Models\Country;
use App\Models\NationalityGroup;
use App\Repositories\Contracts\NationalityGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Country → nationality group resolution (CR-10). Request scoped: holds the nationality of the
 * current request (?nationality=, X-Nationality, or the signed-in user's profile).
 */
class NationalityService
{
    /**
     * Headers set by the edge (Cloudflare) or the Next.js server with the visitor's country.
     */
    public const COUNTRY_HEADERS = ['CF-IPCountry', 'X-Country-Code'];

    private ?string $currentCode = null;

    /**
     * @var Collection<int, NationalityGroup>|null
     */
    private ?Collection $groups = null;

    /**
     * @var array<string, Country|null>
     */
    private array $countries = [];

    public function __construct(private readonly NationalityGroupRepositoryInterface $repository) {}

    /**
     * The group of a country; unknown, missing or unassigned countries get the default group.
     */
    public function groupFor(?string $countryCode): NationalityGroup
    {
        $country = $this->country($countryCode);

        if ($country?->nationality_group_id !== null) {
            $group = $this->groups()->firstWhere('id', $country->nationality_group_id);

            if ($group !== null && $group->is_active) {
                return $group;
            }
        }

        return $this->defaultGroup();
    }

    public function defaultGroup(): NationalityGroup
    {
        return $this->groups()->firstWhere('is_default', true)
            ?? $this->groups()->first()
            ?? throw new RuntimeException('No nationality group is configured.');
    }

    public function defaultGroupId(): int
    {
        return $this->defaultGroup()->id;
    }

    /**
     * Group id to price with: the given group, or the default one.
     */
    public function resolveGroupId(?int $groupId): int
    {
        return $groupId ?? $this->defaultGroupId();
    }

    public function country(?string $code): ?Country
    {
        $code = $this->normalize($code);

        if ($code === null) {
            return null;
        }

        if (! array_key_exists($code, $this->countries)) {
            $this->countries[$code] = $this->repository->findCountry($code);
        }

        return $this->countries[$code];
    }

    public function isKnownCountry(?string $code): bool
    {
        return $this->country($code) !== null;
    }

    /**
     * Country detected from the connection headers, if it is a known ISO country.
     */
    public function detectedCountry(Request $request): ?string
    {
        foreach (self::COUNTRY_HEADERS as $header) {
            $code = $this->normalize($request->header($header));

            if ($code !== null && $this->isKnownCountry($code)) {
                return $code;
            }
        }

        return null;
    }

    public function setCurrent(?string $code): void
    {
        $code = $this->normalize($code);
        $this->currentCode = $code !== null && $this->isKnownCountry($code) ? $code : null;
    }

    public function currentCode(): ?string
    {
        return $this->currentCode;
    }

    public function currentGroup(): NationalityGroup
    {
        return $this->groupFor($this->currentCode);
    }

    /**
     * @return Collection<int, NationalityGroup>
     */
    public function groups(): Collection
    {
        return $this->groups ??= $this->repository->ordered();
    }

    /**
     * Countries with their group (null group = default) for GET /nationalities.
     *
     * @return list<array{code: string, name: string, group: array{id: int, code: string, name: string, is_default: bool}}>
     */
    public function countryList(): array
    {
        $default = $this->defaultGroup();
        $groups = $this->groups()->keyBy('id');

        return $this->repository->countries()
            ->map(fn (Country $country): array => [
                'code' => $country->code,
                'name' => (string) $country->name,
                'group' => ($groups->get($country->nationality_group_id) ?? $default)->summary(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Drops the per-request caches (after an admin change).
     */
    public function flush(): void
    {
        $this->groups = null;
        $this->countries = [];
    }

    private function normalize(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }
}
