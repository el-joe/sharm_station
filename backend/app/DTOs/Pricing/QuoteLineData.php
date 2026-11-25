<?php

namespace App\DTOs\Pricing;

final readonly class QuoteLineData
{
    public const TYPE_ADULT = 'adult';

    public const TYPE_CHILD = 'child';

    public const TYPE_UNIT = 'unit';

    public const TYPE_ADDON = 'addon';

    public const TYPE_NIGHT = 'night';

    public const TYPE_TAX = 'tax';

    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_EXTRA_BED = 'extra_bed';

    public const TYPE_COT = 'cot';

    public const TYPE_ROOM = 'room';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $label,
        public int $quantity,
        public float $unitPrice,
        public float $total,
        public ?float $partnerUnitPrice = null,
        public ?float $partnerTotal = null,
        public ?float $commissionPercent = null,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            label: $data['label'],
            quantity: (int) $data['quantity'],
            unitPrice: (float) $data['unit_price'],
            total: (float) $data['total'],
            partnerUnitPrice: isset($data['partner_unit_price']) ? (float) $data['partner_unit_price'] : null,
            partnerTotal: isset($data['partner_total']) ? (float) $data['partner_total'] : null,
            commissionPercent: isset($data['commission_percent']) ? (float) $data['commission_percent'] : null,
            meta: $data['meta'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $includePartner = false): array
    {
        $line = [
            'type' => $this->type,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total' => $this->total,
            'meta' => (object) $this->meta,
        ];

        if ($includePartner) {
            $line['partner_unit_price'] = $this->partnerUnitPrice;
            $line['partner_total'] = $this->partnerTotal;
            $line['commission_percent'] = $this->commissionPercent;
        }

        return $line;
    }
}
