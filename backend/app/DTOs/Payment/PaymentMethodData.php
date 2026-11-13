<?php

namespace App\DTOs\Payment;

use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin payload of a payment method (gateway or manual).
 */
final readonly class PaymentMethodData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $translated = fn (string $key): ?array => ($map = array_filter((array) ($data[$key] ?? []), fn ($value): bool => filled($value))) === [] ? null : $map;
        $type = PaymentMethodType::from($data['type']);

        return new self([
            'code' => $data['code'],
            'type' => $type,
            'driver' => $type === PaymentMethodType::Online ? ($data['driver'] ?? null) : null,
            'name' => $translated('name'),
            'instructions' => $translated('instructions'),
            'account_details' => array_values(array_filter(
                array_map(fn (array $row): array => ['label' => trim((string) ($row['label'] ?? '')), 'value' => trim((string) ($row['value'] ?? ''))], $data['account_details'] ?? []),
                fn (array $row): bool => $row['label'] !== '' && $row['value'] !== '',
            )) ?: null,
            'currencies' => array_values($data['currencies'] ?? []) ?: null,
            'proof_required' => (bool) ($data['proof_required'] ?? false),
            'surcharge_percent' => (float) ($data['surcharge_percent'] ?? 0),
            'deadline_hours' => filled($data['deadline_hours'] ?? null) ? (int) $data['deadline_hours'] : null,
            'mode' => $data['mode'] ?? 'test',
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }
}
