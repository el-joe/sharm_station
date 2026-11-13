<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomPricingMode;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Room editor (SRS 6.2). Editors cannot change prices: stored values are kept.
 */
class RoomRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const PRICE_FIELDS = ['pricing_mode', 'base_price', 'partner_price', 'extra_bed_price'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amenities' => $this->input('amenities', [])]);

        if ($this->user()?->can('manage-prices')) {
            return;
        }

        $room = $this->route('room');
        $locked = [];

        foreach (self::PRICE_FIELDS as $field) {
            $value = $room instanceof Room ? $room->getAttribute($field) : null;
            $locked[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $locked['pricing_mode'] ??= RoomPricingMode::PerRoom->value;
        $locked['base_price'] ??= 0;
        $this->merge($locked);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.it' => ['nullable', 'string', 'max:160'],
            'number' => ['nullable', 'string', 'max:20'],
            'room_type' => ['required', Rule::enum(RoomType::class)],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'max_total' => ['required', 'integer', 'min:1', 'max:30', 'gte:max_adults'],
            'beds' => ['nullable', 'array'],
            'beds.*' => ['nullable', 'string', 'max:120'],
            'view' => ['nullable', 'array'],
            'view.*' => ['nullable', 'string', 'max:120'],
            'size_m2' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:10000'],
            'amenities' => ['array'],
            'amenities.*' => ['string', 'max:60'],
            'pricing_mode' => ['required', Rule::enum(RoomPricingMode::class)],
            'base_price' => ['required', 'numeric', 'min:0'],
            'partner_price' => ['nullable', 'numeric', 'min:0', 'lte:base_price'],
            'extra_bed_price' => ['nullable', 'numeric', 'min:0'],
            'min_nights' => ['required', 'integer', 'min:1', 'max:365'],
            'max_nights' => ['nullable', 'integer', 'gte:min_nights', 'max:365'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_total.gte' => 'The total capacity must be at least the maximum adults.',
            'partner_price.lte' => 'The partner price cannot be higher than the base price.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        foreach (['beds', 'view', 'description'] as $field) {
            $data[$field] = array_filter($data[$field] ?? [], fn ($value): bool => filled($value)) ?: null;
        }

        return $data;
    }
}
