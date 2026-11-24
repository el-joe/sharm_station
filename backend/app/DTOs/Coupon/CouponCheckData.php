<?php

namespace App\DTOs\Coupon;

use App\Enums\ServiceType;
use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A coupon code to check against a service and a total (EUR), for a customer (user or email) or a partner.
 */
final readonly class CouponCheckData
{
    public function __construct(
        public string $code,
        public ServiceType $serviceType,
        public float $totalEur,
        public ?int $userId = null,
        public ?string $email = null,
        public ?Partner $partner = null,
    ) {}

    public static function fromRequest(FormRequest $request, ?Partner $partner = null): self
    {
        return new self(
            code: strtoupper(trim((string) $request->validated('code'))),
            serviceType: ServiceType::from($request->validated('service_type')),
            totalEur: (float) $request->validated('total_eur'),
            userId: $request->user('sanctum')?->getKey(),
            email: $request->validated('email') ?? $request->user('sanctum')?->email,
            partner: $partner,
        );
    }
}
