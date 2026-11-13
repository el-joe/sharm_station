<?php

namespace App\Services;

use App\DTOs\Pricing\SeasonPriceData;
use App\Enums\PackagePriceMode;
use App\Enums\PricingMode;
use App\Exceptions\DomainValidationException;
use App\Exceptions\SeasonOverlapException;
use App\Models\Package;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Repositories\Contracts\SeasonPriceRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seasonal price lists (CR-7) for trips, packages and rooms.
 */
class SeasonPriceService
{
    public function __construct(private readonly SeasonPriceRepositoryInterface $seasons) {}

    /**
     * The active season containing $date, or null when the base price applies.
     * Specific (non-recurring) periods win over recurring ones.
     */
    public function forDate(Model $priceable, CarbonInterface $date): ?SeasonPrice
    {
        return $this->activeSeasons($priceable)
            ->filter(fn (SeasonPrice $season): bool => $season->covers($date))
            ->sortBy(fn (SeasonPrice $season): string => ($season->recurring_yearly ? '1' : '0').$season->starts_on->format('Y-m-d'))
            ->first();
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
            $overlap = $this->firstOverlap($candidate, $existing);

            if ($overlap !== null) {
                throw SeasonOverlapException::between(
                    $data->displayName(),
                    (string) $existing->name,
                    $this->formatRange($overlap[0], $overlap[1]),
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
                if (! $rows[$previous]['data']->isActive) {
                    continue;
                }

                $overlap = $this->firstOverlap($candidates[$index], $candidates[$previous]);

                if ($overlap !== null) {
                    $message = SeasonOverlapException::between(
                        $row['data']->displayName(),
                        $rows[$previous]['data']->displayName(),
                        $this->formatRange($overlap[0], $overlap[1]),
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
            ...$season->only(['adult_price', 'child_price', 'partner_price', 'unit_prices', 'tier_prices', 'price_per_night', 'recurring_yearly', 'is_active']),
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
                ...$season->only(['adult_price', 'child_price', 'partner_price', 'price_per_night', 'recurring_yearly', 'is_active']),
                'name' => $season->getTranslations('name'),
                'starts_on' => $season->starts_on->toDateString(),
                'ends_on' => $season->ends_on->toDateString(),
            ]));
        }

        return $copies;
    }

    /**
     * Active seasons that are current or upcoming, ordered by their next occurrence.
     *
     * @return Collection<int, array{season: SeasonPrice, starts_on: CarbonImmutable, ends_on: CarbonImmutable, is_current: bool}>
     */
    public function priceList(Model $priceable, ?CarbonInterface $today = null): Collection
    {
        $today = CarbonImmutable::parse(($today ?? now())->toDateString());

        return $this->activeSeasons($priceable)
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
     * Lowest price among the base price and active current/future seasons ("Prices from").
     */
    public function fromPrice(Model $priceable, ?CarbonInterface $today = null): ?float
    {
        $seasons = $this->priceList($priceable, $today)->pluck('season');

        $candidates = match (true) {
            $priceable instanceof Trip => $this->tripPriceCandidates($priceable, $seasons),
            $priceable instanceof Room => [$priceable->base_price, ...$seasons->pluck('price_per_night')],
            $priceable instanceof Package && $priceable->price_mode === PackagePriceMode::Computed => [$this->computedPackagePrice($priceable)],
            $priceable instanceof Package => [$priceable->base_price, ...$seasons->pluck('adult_price')],
            default => [],
        };

        $candidates = array_filter($candidates, fn ($price): bool => $price !== null && (float) $price > 0);

        return $candidates === [] ? null : round((float) min(array_map('floatval', $candidates)), 2);
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
            foreach ($class::query()->lazyById(200) as $priceable) {
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
     * @param  Collection<int, SeasonPrice>  $seasons
     * @return list<mixed>
     */
    private function tripPriceCandidates(Trip $trip, Collection $seasons): array
    {
        if ($trip->pricing_mode === PricingMode::PerUnit) {
            $options = $trip->unitOptions()->get();
            $candidates = $options->pluck('price')->all();

            foreach ($seasons as $season) {
                foreach ($options as $option) {
                    $candidates[] = $season->unitPriceFor($option->id);
                }
            }

            return $candidates;
        }

        $candidates = [];

        if ($trip->group_enabled) {
            $candidates = [$trip->base_price, ...$seasons->pluck('adult_price')];
        }

        if ($trip->private_enabled) {
            $tiers = $trip->privateTiers()->get();
            $candidates = [...$candidates, ...$tiers->pluck('price_per_person')];

            foreach ($seasons as $season) {
                foreach ($tiers as $tier) {
                    $candidates[] = $season->tierPriceFor($tier->id);
                }
            }
        }

        return $candidates;
    }

    /**
     * "From" price of a package priced from its trips: sum of the trips' from prices minus the package discount.
     */
    private function computedPackagePrice(Package $package): ?float
    {
        $tripPrices = $package->stages()->with('stageTrips.trip')->get()
            ->flatMap(fn ($stage) => $stage->stageTrips)
            ->map(fn ($stageTrip): ?float => $stageTrip->trip?->from_price !== null ? (float) $stageTrip->trip->from_price : null)
            ->filter();

        if ($tripPrices->isEmpty()) {
            return null;
        }

        return round($tripPrices->sum() * (1 - ((float) $package->discount_percent) / 100), 2);
    }
}
