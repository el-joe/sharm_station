<?php

namespace App\DTOs\Coupon;

use App\Enums\CouponDiscountType;
use App\Enums\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CouponData
{
    /**
     * @param  list<ServiceType>  $services  empty = every service
     */
    public function __construct(
        public string $code,
        public CouponDiscountType $discountType,
        public float $value,
        public ?string $description = null,
        public ?CarbonImmutable $validFrom = null,
        public ?CarbonImmutable $validTo = null,
        public ?int $usageLimit = null,
        public ?int $usageLimitPerCustomer = null,
        public ?float $minimumTotal = null,
        public array $services = [],
        public bool $partnersAllowed = false,
        public bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key): ?int => filled($data[$key] ?? null) ? (int) $data[$key] : null;
        $date = fn (string $key): ?CarbonImmutable => filled($data[$key] ?? null) ? CarbonImmutable::parse($data[$key])->startOfDay() : null;

        return new self(
            code: strtoupper(trim((string) $data['code'])),
            discountType: CouponDiscountType::from($data['discount_type']),
            value: (float) $data['value'],
            description: filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            validFrom: $date('valid_from'),
            validTo: $date('valid_to'),
            usageLimit: $int('usage_limit'),
            usageLimitPerCustomer: $int('usage_limit_per_customer'),
            minimumTotal: filled($data['minimum_total'] ?? null) ? (float) $data['minimum_total'] : null,
            services: array_values(array_map(ServiceType::from(...), $data['services'] ?? [])),
            partnersAllowed: (bool) ($data['partners_allowed'] ?? false),
            isActive: (bool) ($data['is_active'] ?? false),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discountType,
            'value' => $this->value,
            'valid_from' => $this->validFrom?->toDateString(),
            'valid_to' => $this->validTo?->toDateString(),
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_customer' => $this->usageLimitPerCustomer,
            'minimum_total' => $this->minimumTotal,
            'services' => $this->services === [] ? null : array_map(fn (ServiceType $service): string => $service->value, $this->services),
            'partners_allowed' => $this->partnersAllowed,
            'is_active' => $this->isActive,
        ];
    }
}
