<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackagePriceMode;
use App\Enums\PublishStatus;
use App\Enums\VehicleType;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Package builder (SRS 4.2): basics, stages with trips per day, included transfers, price mode, add-ons.
 */
class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-prices');
    }

    protected function prepareForValidation(): void
    {
        $title = (array) $this->input('title', []);

        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: ($title['en'] ?? ''))),
            'stages' => array_values(array_map(fn ($stage): array => [
                ...(array) $stage,
                'trips' => array_values(array_filter((array) (((array) $stage)['trips'] ?? []), fn ($trip): bool => filled(((array) $trip)['trip_id'] ?? null))),
            ], (array) $this->input('stages', []))),
            'transfers' => array_values((array) $this->input('transfers', [])),
            'featured' => $this->boolean('featured'),
            'private_enabled' => $this->boolean('private_enabled'),
            'group_enabled' => $this->boolean('group_enabled'),
            'addon_ids' => array_values(array_filter((array) $this->input('addon_ids', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Package|null $package */
        $package = $this->route('package');

        return [
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:180'],
            'title.it' => ['nullable', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('packages', 'slug')->ignore($package?->id)],
            'summary' => ['nullable', 'array'],
            'summary.*' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:10000'],
            'cancellation_policy' => ['nullable', 'array'],
            'cancellation_policy.*' => ['nullable', 'string', 'max:2000'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['nullable', 'string', 'max:3000'],
            'included' => ['nullable', 'array'],
            'included.*' => ['nullable', 'string', 'max:3000'],
            'excluded' => ['nullable', 'array'],
            'excluded.*' => ['nullable', 'string', 'max:3000'],
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'nights' => ['required', 'integer', 'min:0', 'max:60'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'featured' => ['boolean'],
            'private_enabled' => ['boolean'],
            'group_enabled' => ['boolean'],
            'min_persons' => ['required', 'integer', 'min:1', 'max:100'],
            'max_persons' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:min_persons'],
            'price_mode' => ['required', Rule::enum(PackagePriceMode::class)],
            'base_price' => ['nullable', 'required_if:price_mode,fixed', 'numeric', 'min:0'],
            'base_child_price' => ['nullable', 'numeric', 'min:0'],
            'partner_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'free_cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'stages' => ['required', 'array', 'min:1', 'max:20'],
            'stages.*.destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'stages.*.days' => ['required', 'integer', 'min:1', 'max:60'],
            'stages.*.nights' => ['nullable', 'integer', 'min:0', 'max:60'],
            'stages.*.accommodation' => ['nullable', 'array'],
            'stages.*.accommodation.*' => ['nullable', 'string', 'max:255'],
            'stages.*.trips' => ['nullable', 'array', 'max:40'],
            'stages.*.trips.*.trip_id' => ['required', 'integer', 'exists:trips,id'],
            'stages.*.trips.*.day_number' => ['required', 'integer', 'min:1', 'max:60'],
            'transfers' => ['nullable', 'array', 'max:40'],
            'transfers.*.day_number' => ['required', 'integer', 'min:1', 'max:60'],
            'transfers.*.time' => ['nullable', 'date_format:H:i'],
            'transfers.*.from_place' => ['required', 'string', 'max:255'],
            'transfers.*.to_place' => ['required', 'string', 'max:255'],
            'transfers.*.vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'addon_ids' => ['array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
        ];
    }

    /**
     * Stage days must add up to the package duration; trips and transfers must fall inside it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $stages = $this->input('stages', []);
                $total = array_sum(array_map(fn (array $stage): int => (int) $stage['days'], $stages));

                if ($total !== (int) $this->input('days')) {
                    $validator->errors()->add('stages', "The stages last {$total} days but the package lasts {$this->input('days')} days.");
                }

                if (! $this->boolean('private_enabled') && ! $this->boolean('group_enabled')) {
                    $validator->errors()->add('private_enabled', 'Enable private or group bookings.');
                }

                foreach ($stages as $index => $stage) {
                    foreach ($stage['trips'] ?? [] as $tripIndex => $trip) {
                        if ((int) $trip['day_number'] > (int) $stage['days']) {
                            $validator->errors()->add("stages.{$index}.trips.{$tripIndex}.day_number", 'Day '.$trip['day_number'].' is outside stage '.($index + 1).' ('.$stage['days'].' days).');
                        }
                    }
                }

                foreach ($this->input('transfers', []) as $index => $transfer) {
                    if ((int) $transfer['day_number'] > (int) $this->input('days')) {
                        $validator->errors()->add("transfers.{$index}.day_number", 'Day '.$transfer['day_number'].' is after the end of the package.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.en' => 'English title',
            'stages.*.destination_id' => 'stage destination',
            'stages.*.days' => 'stage days',
            'stages.*.trips.*.trip_id' => 'trip',
            'transfers.*.from_place' => 'from',
            'transfers.*.to_place' => 'to',
        ];
    }
}
