<?php

namespace App\Services;

use App\DTOs\Report\ReportFilterData;
use App\Enums\PaymentType;
use App\Enums\ServiceType;
use App\Models\Room;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Reports (A6, SRS 14.1): KPIs, tables and charts aggregated in SQL; every table exports to CSV.
 */
class ReportService
{
    /**
     * Report tables => title.
     *
     * @var array<string, string>
     */
    public const TABLES = [
        'revenue' => 'Revenue by service and month',
        'best-sellers' => 'Best sellers',
        'partners' => 'Partner sales and commissions',
        'nationalities' => 'Nationality groups',
        'payments' => 'Payments by method',
    ];

    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * @return array<string, float|int>
     */
    public function kpis(ReportFilterData $filters): array
    {
        return $this->reports->kpis($filters);
    }

    /**
     * One table as headings + rows (raw values; the view formats money).
     *
     * @return array{title: string, headings: list<string>, money: list<int>, rows: list<list<string|int|float|null>>}
     */
    public function table(string $table, ReportFilterData $filters): array
    {
        return match ($table) {
            'revenue' => [
                'title' => self::TABLES[$table],
                'headings' => ['Month', 'Service', 'Bookings', 'Revenue (EUR)'],
                'money' => [3],
                'rows' => array_map(fn (array $row): array => [$row['period'], ServiceType::tryFrom($row['service_type'])?->label() ?? $row['service_type'], $row['bookings'], $row['revenue']],
                    $this->reports->revenueByServiceAndMonth($filters)),
            ],
            'best-sellers' => [
                'title' => self::TABLES[$table],
                'headings' => ['Product', 'Service', 'Bookings', 'Persons', 'Revenue (EUR)'],
                'money' => [4],
                'rows' => $this->bestSellerRows($filters),
            ],
            'partners' => [
                'title' => self::TABLES[$table],
                'headings' => ['Partner', 'Bookings', 'Customer prices (EUR)', 'Partner sales (EUR)', 'Commission (EUR)'],
                'money' => [2, 3, 4],
                'rows' => array_map(fn (array $row): array => [$row['company'], $row['bookings'], $row['retail_total'], $row['partner_total'], $row['commission']],
                    $this->reports->partnerSales($filters)),
            ],
            'nationalities' => [
                'title' => self::TABLES[$table],
                'headings' => ['Nationality group', 'Bookings', 'Revenue (EUR)'],
                'money' => [2],
                'rows' => array_map(fn (array $row): array => [$row['name'] ?? 'Not set', $row['bookings'], $row['revenue']],
                    $this->reports->nationalityMix($filters)),
            ],
            'payments' => [
                'title' => self::TABLES[$table],
                'headings' => ['Method', 'Type', 'Payments', 'Amount (EUR)'],
                'money' => [3],
                'rows' => array_map(fn (array $row): array => [$row['method'] ?? 'Manual / cash', PaymentType::tryFrom($row['type'])?->label() ?? $row['type'], $row['payments'], $row['type'] === PaymentType::Refund->value ? -$row['amount'] : $row['amount']],
                    $this->reports->paymentsByMethod($filters)),
            ],
        };
    }

    /**
     * @return array<string, array{title: string, headings: list<string>, money: list<int>, rows: list<list<string|int|float|null>>}>
     */
    public function tables(ReportFilterData $filters): array
    {
        return collect(array_keys(self::TABLES))->mapWithKeys(fn (string $table): array => [$table => $this->table($table, $filters)])->all();
    }

    /**
     * Chart.js data: revenue per month (stacked by service) and the nationality mix.
     *
     * @param  array<string, array{rows: list<list<mixed>>}>  $tables
     * @return array{revenue: array<string, mixed>, nationalities: array<string, mixed>}
     */
    public function charts(ReportFilterData $filters, array $tables): array
    {
        $months = [];

        for ($month = $filters->from->startOfMonth(); $month->lte($filters->to); $month = $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        $byService = [];

        foreach ($this->reports->revenueByServiceAndMonth($filters) as $row) {
            $byService[$row['service_type']][$row['period']] = $row['revenue'];
        }

        $colors = ['trip' => '#101a5c', 'nile_trip' => '#2c74b3', 'package' => '#ee8431', 'transfer' => '#1f8f5f', 'accommodation' => '#c0550a'];

        return [
            'revenue' => [
                'labels' => array_map(fn (string $month): string => date('M Y', strtotime($month.'-01')), $months),
                'datasets' => collect($byService)->map(fn (array $values, string $service): array => [
                    'label' => ServiceType::tryFrom($service)?->label() ?? $service,
                    'data' => array_map(fn (string $month): float => $values[$month] ?? 0.0, $months),
                    'color' => $colors[$service] ?? '#6a6f8f',
                ])->values()->all(),
            ],
            'nationalities' => [
                'labels' => array_map(fn (array $row): string => (string) $row[0], $tables['nationalities']['rows']),
                'data' => array_map(fn (array $row): int => (int) $row[1], $tables['nationalities']['rows']),
            ],
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    private function bestSellerRows(ReportFilterData $filters): array
    {
        $rows = $this->reports->bestSellers($filters);
        $names = [];

        foreach (collect($rows)->groupBy('bookable_type') as $type => $group) {
            $class = Relation::getMorphedModel((string) $type);

            if ($class === null) {
                continue;
            }

            $class::query()->whereKey($group->pluck('bookable_id')->filter()->all())
                ->when($class === Room::class, fn ($query) => $query->with('building:id,name'))
                ->get()
                ->each(function (Model $model) use (&$names, $type): void {
                    $label = (string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? '#'.$model->getKey());
                    $names[$type.':'.$model->getKey()] = $model instanceof Room && $model->building ? $model->building->name.' – '.$label : $label;
                });
        }

        return array_map(fn (array $row): array => [
            $names[$row['bookable_type'].':'.$row['bookable_id']] ?? Str::headline((string) $row['bookable_type']).' #'.$row['bookable_id'],
            ServiceType::tryFrom($row['service_type'])?->label() ?? $row['service_type'],
            $row['bookings'],
            $row['persons'],
            $row['revenue'],
        ], $rows);
    }
}
