<?php

namespace Database\Seeders\Concerns;

use App\DTOs\Pricing\SeasonPriceData;
use App\Services\SeasonPriceService;
use Illuminate\Database\Eloquent\Model;

trait SeedsSeasons
{
    /**
     * Standard recurring price periods. Each entry: [key, en, it, starts_on, ends_on].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    protected function seasonCalendar(): array
    {
        return [
            ['low', 'Low season', 'Bassa stagione', '2027-01-07', '2027-03-31'],
            ['spring', 'Spring', 'Primavera', '2027-04-01', '2027-05-31'],
            ['summer', 'Summer', 'Estate', '2026-06-01', '2026-09-30'],
            ['high', 'High season', 'Alta stagione', '2026-11-01', '2026-12-19'],
            ['christmas', 'Christmas & New Year', 'Natale e Capodanno', '2026-12-20', '2027-01-06'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $pricesBySeason  season key => price attributes
     */
    protected function seedSeasons(Model $priceable, array $pricesBySeason): void
    {
        $service = app(SeasonPriceService::class);

        foreach ($this->seasonCalendar() as [$key, $en, $it, $startsOn, $endsOn]) {
            if (! isset($pricesBySeason[$key])) {
                continue;
            }

            $service->save($priceable, SeasonPriceData::fromArray([
                ...$pricesBySeason[$key],
                'name' => ['en' => $en, 'it' => $it],
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'recurring_yearly' => true,
                'is_active' => true,
            ]));
        }

        $service->refreshFromPrice($priceable);
    }
}
