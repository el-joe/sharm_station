<?php

namespace App\DTOs\Coupon;

use App\Models\Coupon;

/**
 * Outcome of a coupon check: valid with its discount, or invalid with the reason.
 */
final readonly class CouponResultData
{
    public function __construct(
        public bool $valid,
        public string $code,
        public string $message,
        public ?Coupon $coupon = null,
        public float $discountEur = 0.0,
    ) {}

    public static function invalid(string $code, string $message, ?Coupon $coupon = null): self
    {
        return new self(false, $code, $message, $coupon);
    }

    /**
     * @return array{valid: bool, code: string, discount_type: string|null, value: float|null, discount_eur: float, message: string}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'code' => $this->code,
            'discount_type' => $this->valid ? $this->coupon?->discount_type->value : null,
            'value' => $this->valid && $this->coupon !== null ? (float) $this->coupon->value : null,
            'discount_eur' => $this->discountEur,
            'message' => $this->message,
        ];
    }
}
