<?php

namespace App\DTOs\Report;

use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reports filters (A6): booking creation date range and optional service. Defaults to the last 12 months.
 */
final readonly class ReportFilterData
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?ServiceType $serviceType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $to = filled($data['date_to'] ?? null) ? CarbonImmutable::parse($data['date_to'])->endOfDay() : CarbonImmutable::today()->endOfDay();
        $from = filled($data['date_from'] ?? null) ? CarbonImmutable::parse($data['date_from'])->startOfDay() : $to->subMonths(11)->startOfMonth();

        return new self($from, $to, ServiceType::tryFrom((string) ($data['service_type'] ?? '')));
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'date_from' => $this->from->toDateString(),
            'date_to' => $this->to->toDateString(),
            'service_type' => $this->serviceType?->value,
        ]);
    }
}
