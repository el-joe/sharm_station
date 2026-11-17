<?php

namespace App\Services;

use App\DTOs\Pricing\SeasonPriceData;
use App\Enums\PackagePriceMode;
use App\Exceptions\DomainValidationException;
use App\Exceptions\SeasonOverlapException;
use App\Models\Contracts\Priceable;
use App\Models\Package;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;
use App\Repositories\Contracts\SeasonPriceRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seasonal price lists (CR-7) per nationality group (CR-10) for trips, packages, rooms and transfer fixed routes.
 * Seasons of the same product and group cannot overlap; a group season may overlap an all-groups season and wins over it.
 */
class SeasonPriceService
{
    public function __construct(
        private readonly SeasonPriceRepositoryInterface $seasons,
        private readonly ProductPriceRepositoryInterface $productPrices,
        private readonly NationalityService $nationalities,
    ) {}

    /**
     * The season that applies on $date for a group (its own season, else a season for all groups),
     * or null when the base price applies. Without a group only all-groups seasons are considered.
     */
    public function forDate(Model $priceable, CarbonInterface $date, ?int $nationalityGroupId = null): ?SeasonPrice
    {
        [$groupSeason, $allGroupsSeason] = $this->seasonsForDate($priceable, $date, $nationalityGroupId);

        return $groupSeason ?? $allGroupsSeason;
    }

    /**
     * The group's own season and the all-groups season containing $date.
     * Specific (non-recurring) periods win over recurring ones.
     *
     * @return array{0: SeasonPrice|null, 1: SeasonPrice|null}
     */
    public function seasonsForDate(Model $priceable, CarbonInterface $date, ?int $nationalityGroupId): array
    {
        $covering = $this->activeSeasons($priceable)
            ->filter(fn (SeasonPrice $season): bool => $season->covers($date))
            ->sortBy(fn (SeasonPrice $season): string => ($season->recurring_yearly ? '1' : '0').$season->starts_on->format('Y-m-d'));

        $groupSeason = $nationalityGroupId !== null
            ? $covering->first(fn (SeasonPrice $season): bool => $season->nationality_group_id === $nationalityGroupId)
            : null;

        return [$groupSeason, $covering->first(fn (SeasonPrice $season): bool => $season->nationality_group_id === null)];
    }

    /**
     * @throws SeasonOverlapException
     * @throws DomainValidationException
     */
    public function validateNoOverlap(Model $priceable, SeasonPriceData $data, ?int $ignoreSeasonId = null): void
    {
        $this->assertValidPeriod($data);

        if (! $data->isActive) {
            return;
        }

        $candidate = new SeasonPrice($data->toAttributes());

        foreach ($this->seasons->activeFor($priceable, $ignoreSeasonId) as $existing) {
            if ($existing->nationality_group_id !== $data->nationalityGroupId) {
                continue;
            }

            $overlap = $this->firstOverlap($candidate, $existing);

            if ($overlap !== null) {
                throw SeasonOverlapException::between(
                    $data->displayName(),
                    (string) $existing->name,
                    $this->formatRange($overlap[0], $overlap[1]),
                    $this->groupLabel($data->nationalityGroupId),
                );
            }
        }
    }

    /**
     * Replaces the whole price list of a product from the admin Price list tab.
     * Rows are validated against each other (the editor submits every season at once);
     * error keys point at the offending row, e.g. "seasons.2.starts_on".
     *
     * @param  list<array{id: int|null, data: SeasonPriceData}>  $rows
     * @return \Illuminate\Database\Eloquent\Collection<int, SeasonPrice>
     *
     * @throws SeasonOverlapException
     * @throws DomainValidationException
     */
    public function sync(Model $priceable, array $rows): \Illuminate\Database\Eloquent\Collection
    {
        $rows = array_values($rows);
        $candidates = [];

        foreach ($rows as $index => $row) {
            $this->assertValidPeriod($row['data'], "seasons.{$index}.");
            $candidates[$index] = new SeasonPrice($row['data']->toAttributes());
        }

        foreach ($rows as $index => $row) {
            if (! $row['data']->isActive) {
                continue;
            }

            for ($previous = 0; $previous < $index; $previous++) {
                if (! $rows[$previous]['data']->isActive || $rows[$previous]['data']->nationalityGroupId !== $row['data']->nationalityGroupId) {
                    continue;
                }

                $overlap = $this->firstOverlap($candidates[$index], $candidates[$previous]);

                if ($overlap !== null) {
                    $message = SeasonOverlapException::between(
                        $row['data']->displayName(),
                        $rows[$previous]['data']->displayName(),
                        $this->formatRange($overlap[0], $overlap[1]),
                        $this->groupLabel($row['data']->nationalityGroupId),
                    )->getMessage();

                    throw new SeasonOverlapException($message, ["seasons.{$index}.starts_on" => [$message]]);
                }
            }
        }

        DB::transaction(function () use ($priceable, $rows): void {
            $existing = $this->seasons->allFor($priceable)->keyBy('id');
            $keep = [];

            foreach ($rows as $row) {
                $season = $row['id'] !== null ? $existing->get($row['id']) : null;

                $season = $season !== null
                    ? $this->seasons->update($season, $row['data']->toAttributes())
                    : $this->seasons->create([
                        ...$row['data']->toAttributes(),
                        'priceable_type' => $priceable->getMorphClass(),
                        'priceable_id' => $priceable->getKey(),
                    ]);

                $keep[] = $season->getKey();
            }

            $existing->except($keep)->each(fn (SeasonPrice $season): bool => $this->seasons->delete($season));
        });

        $this->refreshFromPrice($priceable);

        return $this->seasons->allFor($priceable);
    }

    /**
     * Every season of a product (active and inactive) ordered by start date, for the editor.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SeasonPrice>
     */
    public function allFor(Model $priceable): \Illuminate\Database\Eloquent\Collection
    {
        return $this->seasons->allFor($priceable);
    }

    /**
     * Creates or updates a season after the overlap check and refreshes the product's "from" price.
     */
    public function save(Model $priceable, SeasonPriceData $data, ?SeasonPrice $season = null): SeasonPrice
    {
        $this->validateNoOverlap($priceable, $data, $season?->getKey());

        if ($season === null) {
            $season = $this->seasons->create([
                ...$data->toAttributes(),
                'priceable_type' => $priceable->getMorphClass(),
                'priceable_id' => $priceable->getKey(),
            ]);
        } else {
            $season = $this->seasons->update($season, $data->toAttributes());
        }

        $this->refreshFromPrice($priceable);

        return $season;
    }

    public function delete(SeasonPrice $season): void
    {
        $priceable = $season->priceable;
        $this->seasons->delete($season);

        if ($priceable !== null) {
            $this->refreshFromPrice($priceable);
        }
    }

    public function copyToNextYear(SeasonPrice $season): SeasonPrice
    {
        $data = SeasonPriceData::fromArray([
            ...$season->only(['nationality_group_id', 'adult_price', 'child_price', 'unit_prices', 'tier_prices', 'price_per_night', 'price', 'recurring_yearly', 'is_active']),
            'name' => $season->getTranslations('name'),
            'starts_on' => $season->starts_on->addYear()->toDateString(),
            'ends_on' => $season->ends_on->addYear()->toDateString(),
        ]);

        return $this->save($season->priceable, $data);
    }

    /**
     * Copies a season's dates and per-person/night prices to other products (unit and tier prices are product specific).
     *
     * @param  iterable<Model>  $targets
     * @return list<SeasonPrice>
     */
    public function copyToProducts(SeasonPrice $season, iterable $targets): array
    {
        $copies = [];

        foreach ($targets as $target) {
            $copies[] = $this->save($target, SeasonPriceData::fromArray([
                ...$season->only(['nationality_group_id', 'adult_price', 'child_price', 'price_per_night', 'price', 'recurring_yearly', 'is_active']),
                'name' => $season->getTranslations('name'),
                'starts_on' => $season->starts_on->toDateString(),
                'ends_on' => $season->ends_on->toDateString(),
            ]));
        }

        return $copies;
    }

    /**
     * Active seasons that are current or upcoming, ordered by their next occurrence.
     * With a group: that group's seasons and the all-groups ones; without: only all-groups seasons.
     *
     * @return Collection<int, array{season: SeasonPrice, starts_on: CarbonImmutable, ends_on: CarbonImmutable, is_current: bool}>
     */
    public function priceList(Model $priceable, ?CarbonInterface $today = null, ?int $nationalityGroupId = null): Collection
    {
        $today = CarbonImmutable::parse(($today ?? now())->toDateString());

        return $this->activeSeasons($priceable)
            ->filter(fn (SeasonPrice $season): bool => $season->appliesToGroup($nationalityGroupId))
            ->filter(fn (SeasonPrice $season): bool => $season->isCurrentOrFuture($today))
            ->map(function (SeasonPrice $season) use ($today): array {
                [$startsOn, $endsOn] = $this->nextOccurrence($season, $today);

                return [
                    'season' => $season,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'is_current' => $season->covers($today),
                ];
            })
            ->sortBy(fn (array $row): string => $row['starts_on']->toDateString())
            ->values();
    }

    /**
     * Dates of the occurrence that contains or follows $today (recurring seasons are shifted by whole years).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function nextOccurrence(SeasonPrice $season, CarbonInterface $today): array
    {
        $startsOn = $season->starts_on;
        $endsOn = $season->ends_on;

        if (! $season->recurring_yearly) {
            return [$startsOn, $endsOn];
        }

        $years = $today->year - $endsOn->year;
        $startsOn = $startsOn->addYears($years);
        $endsOn = $endsOn->addYears($years);

        if ($endsOn->format('Y-m-d') < $today->format('Y-m-d')) {
            $startsOn = $startsOn->addYear();
            $endsOn = $endsOn->addYear();
        }

        return [$startsOn, $endsOn];
    }

    /**
     * Lowest customer price for a nationality group among its base prices and the active current/future
     * seasons that apply to it ("Prices from"). Without a group the default group is used.
     */
    public function fromPrice(Model $priceable, ?CarbonInterface $today = null, ?int $nationalityGroupId = null): ?float
    {
        if (! $priceable instanceof Priceable) {
            return null;
        }

        $groupId = $this->nationalities->resolveGroupId($nationalityGroupId);

        if ($priceable instanceof Package && $priceable->price_mode === PackagePriceMode::Computed) {
            return $this->computedPackagePrice($priceable, $today, $groupId);
        }

        $seasons = $this->priceList($priceable, $today, $groupId)->pluck('season');
        $groupBase = $this->groupBase($priceable, $groupId);
        $candidates = [];

        foreach (array_keys($this->fromPriceFields($priceable)) as $field) {
            $candidates[] = $groupBase?->priceFor($field) ?? $priceable->basePriceFor($field);

            foreach ($seasons as $season) {
                $candidates[] = $season->priceFor($field);
            }
        }

        $candidates = array_filter($candidates, fn (?float $price): bool => $price !== null && $price > 0);

        return $candidates === [] ? null : round(min($candidates), 2);
    }

    /**
     * "From" price for a group, using the stored default-group price when the product has no prices that
     * differ by group (no group base prices and no group seasons): then every group shares it.
     */
    public function groupFromPrice(Model $priceable, ?int $nationalityGroupId): ?float
    {
        $stored = $priceable->getAttribute('from_price');
        $stored = $stored !== null ? (float) $stored : null;

        if (! $priceable instanceof Priceable || ! $this->hasGroupPrices($priceable)) {
            return $stored;
        }

        return $this->fromPrice($priceable, null, $nationalityGroupId) ?? $stored;
    }

    /**
     * Whether any price of the product depends on the nationality group.
     */
    public function hasGroupPrices(Model $priceable): bool
    {
        if (! method_exists($priceable, 'productPrices')) {
            return false;
        }

        $priceable->loadMissing(['productPrices', 'seasonPrices']);

        return $priceable->getRelation('productPrices')->isNotEmpty()
            || $priceable->getRelation('seasonPrices')->contains(fn (SeasonPrice $season): bool => $season->nationality_group_id !== null);
    }

    /**
     * Group base price row of a product (uses the loaded relation when present).
     */
    public function groupBase(Model $priceable, int $groupId): ?ProductPrice
    {
        if (! method_exists($priceable, 'productPrices')) {
            return null;
        }

        if (! $priceable->relationLoaded('productPrices')) {
            $priceable->setRelation('productPrices', $this->productPrices->allFor($priceable));
        }

        return $priceable->getRelation('productPrices')->firstWhere('nationality_group_id', $groupId);
    }

    /**
     * Recomputes the "from" price of every trip, package and room (seasons start and expire over time).
     *
     * @return array{trips: int, packages: int, rooms: int, changed: int}
     */
    public function refreshAllFromPrices(): array
    {
        $counts = ['trips' => 0, 'packages' => 0, 'rooms' => 0, 'changed' => 0];

        foreach (['trips' => Trip::class, 'packages' => Package::class, 'rooms' => Room::class] as $key => $class) {
            foreach ($class::query()->with(['seasonPrices', 'productPrices'])->lazyById(200) as $priceable) {
                $before = $priceable->getAttribute('from_price');
                $this->refreshFromPrice($priceable);
                $counts[$key]++;

                if ((string) $before !== (string) $priceable->getAttribute('from_price')) {
                    $counts['changed']++;
                }
            }
        }

        return $counts;
    }

    public function refreshFromPrice(Model $priceable): void
    {
        if (! in_array('from_price', $priceable->getFillable(), true)) {
            return;
        }

        $priceable->unsetRelation('seasonPrices');
        $priceable->unsetRelation('productPrices');
        $priceable->unsetRelation('stages');
        $priceable->forceFill(['from_price' => $this->fromPrice($priceable)])->saveQuietly();
    }

    public function formatRange(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('j').'–'.$end->format('j M Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('j M').' – '.$end->format('j M Y');
        }

        return $start->format('j M Y').' – '.$end->format('j M Y');
    }

    /**
     * @throws DomainValidationException
     */
    private function assertValidPeriod(SeasonPriceData $data, string $errorPrefix = ''): void
    {
        if ($data->endsOn->lt($data->startsOn)) {
            throw new DomainValidationException('The season must end on or after its start date.', [$errorPrefix.'ends_on' => ['The season must end on or after its start date.']]);
        }

        if ($data->recurringYearly && $data->startsOn->diffInDays($data->endsOn) > 365) {
            throw new DomainValidationException('A recurring season cannot be longer than one year.', [$errorPrefix.'ends_on' => ['A recurring season cannot be longer than one year.']]);
        }
    }

    /**
     * @return Collection<int, SeasonPrice>
     */
    private function activeSeasons(Model $priceable): Collection
    {
        $seasons = $priceable->relationLoaded('seasonPrices')
            ? $priceable->getRelation('seasonPrices')
            : $this->seasons->activeFor($priceable);

        return collect($seasons)->filter(fn (SeasonPrice $season): bool => $season->is_active)->values();
    }

    /**
     * First contiguous run of days on which both seasons apply.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function firstOverlap(SeasonPrice $candidate, SeasonPrice $existing): ?array
    {
        return $this->firstRunCoveredBy($candidate, $existing) ?? $this->firstRunCoveredBy($existing, $candidate);
    }

    /**
     * Walks the concrete days of $walked and returns the first run that $other also covers.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function firstRunCoveredBy(SeasonPrice $walked, SeasonPrice $other): ?array
    {
        $runStart = null;
        $runEnd = null;

        for ($day = $walked->starts_on; $day->lte($walked->ends_on); $day = $day->addDay()) {
            if ($other->covers($day)) {
                $runStart ??= $day;
                $runEnd = $day;
            } elseif ($runStart !== null) {
                break;
            }
        }

        return $runStart !== null ? [$runStart, $runEnd] : null;
    }

    /**
     * Fields that make up the "from" price (child prices are never the headline price).
     *
     * @return array<string, string>
     */
    private function fromPriceFields(Priceable $priceable): array
    {
        return array_diff_key($priceable->priceFields(), ['child_price' => true]);
    }

    /**
     * Group shown in overlap messages; all-groups seasons need no label.
     */
    private function groupLabel(?int $groupId): ?string
    {
        if ($groupId === null) {
            return null;
        }

        return (string) ($this->nationalities->groups()->firstWhere('id', $groupId)?->name ?? 'this group');
    }

    /**
     * "From" price of a package priced from its trips: sum of the trips' from prices for the group minus the package discount.
     */
    private function computedPackagePrice(Package $package, ?CarbonInterface $today, int $groupId): ?float
    {
        // Uses the eager loaded stages/trips of catalogue listings (Package::COMPUTED_PRICE_RELATIONS).
        $package->loadMissing(Package::COMPUTED_PRICE_RELATIONS);

        $tripPrices = $package->stages
            ->flatMap(fn ($stage) => $stage->stageTrips)
            ->map(fn ($stageTrip): ?float => $stageTrip->trip !== null ? $this->groupFromPrice($stageTrip->trip, $groupId) : null)
            ->filter();

        if ($tripPrices->isEmpty()) {
            return null;
        }

        return round($tripPrices->sum() * (1 - ((float) $package->discount_percent) / 100), 2);
    }
}
