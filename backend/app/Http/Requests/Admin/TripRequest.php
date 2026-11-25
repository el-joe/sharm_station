<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Trip / Nile trip editor (all tabs except the price list, which has its own form).
 * Editors cannot change prices: their pricing input is replaced by the stored values.
 */
class TripRequest extends FormRequest
{
    /**
     * Fields only super admins and agents may change.
     *
     * @var list<string>
     */
    public const PRICE_FIELDS = ['pricing_mode', 'min_persons', 'max_persons', 'base_price', 'base_child_price', 'deposit_percent'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = (array) $this->input('title', []);

        $this->merge([
            'days' => $this->input('days', 1),
            'nights' => $this->input('nights', 0),
            'slug' => Str::slug((string) ($this->input('slug') ?: ($title['en'] ?? ''))),
            'type' => $this->input('type') ?: ($this->trip()?->type?->value ?? TripType::SingleDay->value),
        ]);

        $this->normaliseContentFields();

        if ($this->user()?->can('manage-prices')) {
            // The Pricing tab always posts its lists: an absent list means every row was removed.
            $this->merge([
                'unit_options' => $this->input('unit_options', []),
                'private_tiers' => $this->input('private_tiers', []),
            ]);

            return;
        }

        $trip = $this->trip();
        $locked = [];

        foreach (self::PRICE_FIELDS as $field) {
            $value = $trip?->getAttribute($field);
            $locked[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $locked['pricing_mode'] ??= PricingMode::PerPerson->value;
        $locked['deposit_percent'] ??= 30;
        $locked['min_persons'] ??= 1;

        $this->merge($locked);
        $this->request->remove('unit_options');
        $this->request->remove('private_tiers');
    }

    /**
     * Editor-only inputs → stored shapes: "what_to_bring_lines" (one item per line) → what_to_bring {locale: list},
     * "faq_rows" (one row per question with EN/IT) → faq {locale: list<{question, answer}>}.
     */
    private function normaliseContentFields(): void
    {
        if ($this->has('what_to_bring_lines')) {
            $this->merge(['what_to_bring' => collect((array) $this->input('what_to_bring_lines'))
                ->map(fn ($text): array => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: []), fn (string $line): bool => $line !== '')))
                ->filter()
                ->all() ?: null]);
            $this->request->remove('what_to_bring_lines');
        }

        if ($this->has('faq_present')) {
            $faq = [];

            foreach (array_values((array) $this->input('faq_rows', [])) as $row) {
                foreach (['en', 'it'] as $locale) {
                    $question = trim((string) ($row['question'][$locale] ?? ''));
                    $answer = trim((string) ($row['answer'][$locale] ?? ''));

                    if ($question !== '' || $answer !== '') {
                        $faq[$locale][] = ['question' => $question, 'answer' => $answer];
                    }
                }
            }

            $this->merge(['faq' => $faq ?: null]);
            $this->request->remove('faq_rows');
            $this->request->remove('faq_present');
        }

        if ($this->has('guide_languages_present')) {
            $this->merge(['guide_languages' => array_values(array_unique(array_filter((array) $this->input('guide_languages', [])))) ?: null]);
            $this->request->remove('guide_languages_present');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $trip = $this->trip();
        $perUnit = $this->input('pricing_mode') === PricingMode::PerUnit->value;
        $canPrice = (bool) $this->user()?->can('manage-prices');

        return [
            // Basics
            'type' => ['required', Rule::enum(TripType::class)],
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'category' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:160'],
            'title.it' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('trips', 'slug')->ignore($trip?->id)],
            'summary' => ['nullable', 'array'],
            'summary.*' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
            'featured' => ['boolean'],
            'private_enabled' => ['boolean'],
            'group_enabled' => ['boolean'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'nights' => ['required', 'integer', 'min:0', 'max:60'],
            'free_cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],

            // Trip details shown on the website (unit label, FAQ, what to bring, guide languages, cancellation text)
            'unit_label' => ['nullable', 'array'],
            'unit_label.*' => ['nullable', 'string', 'max:40'],
            'faq' => ['nullable', 'array'],
            'faq.*' => ['array', 'max:30'],
            'faq.*.*.question' => ['required', 'string', 'max:255'],
            'faq.*.*.answer' => ['required', 'string', 'max:2000'],
            'what_to_bring' => ['nullable', 'array'],
            'what_to_bring.*' => ['array', 'max:30'],
            'what_to_bring.*.*' => ['string', 'max:200'],
            'guide_languages' => ['nullable', 'array'],
            'guide_languages.*' => ['string', Rule::in(array_keys((array) trans('catalog.languages', [], 'en')))],
            'cancellation_policy' => ['nullable', 'array'],
            'cancellation_policy.*' => ['nullable', 'string', 'max:2000'],

            // Meeting point
            'meeting_point' => ['nullable', 'array'],
            'meeting_point.*' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],

            // Pricing
            'pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'min_persons' => ['required', 'integer', 'min:1', 'max:999'],
            'max_persons' => ['nullable', 'integer', 'min:1', 'max:999', 'gte:min_persons'],
            'deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'base_price' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($canPrice && ! $perUnit && $this->boolean('group_enabled'))],
            'base_child_price' => ['nullable', 'numeric', 'min:0'],
            'private_tiers' => ['sometimes', 'array', Rule::requiredIf($canPrice && ! $perUnit && $this->boolean('private_enabled'))],
            'private_tiers.*.id' => ['nullable', 'integer'],
            'private_tiers.*.min_people' => ['required', 'integer', 'min:1'],
            'private_tiers.*.max_people' => ['nullable', 'integer', 'gte:private_tiers.*.min_people'],
            'private_tiers.*.price_per_person' => ['required', 'numeric', 'min:0'],
            'unit_options' => ['sometimes', 'array', Rule::requiredIf($canPrice && $perUnit)],
            'unit_options.*.id' => ['nullable', 'integer'],
            'unit_options.*.name' => ['required', 'array'],
            'unit_options.*.name.en' => ['required', 'string', 'max:80'],
            'unit_options.*.name.it' => ['nullable', 'string', 'max:80'],
            'unit_options.*.seats' => ['required', 'integer', 'min:1', 'max:50'],
            'unit_options.*.price' => ['required', 'numeric', 'min:0'],
            'unit_options.*.max_quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_options.*.children_allowed' => ['boolean'],
            'unit_options.*.sort' => ['nullable', 'integer'],

            // Availability
            'availability' => ['nullable', 'array'],
            'availability.private.lead_time_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'availability.private.blackout_dates' => ['nullable', 'string', 'max:5000'],
            'availability.group.lead_time_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'availability.group.weekdays' => ['nullable', 'array'],
            'availability.group.weekdays.*' => ['integer', 'between:1,7'],
            'availability.group.dates' => ['nullable', 'string', 'max:5000'],
            'availability.group.blackout_dates' => ['nullable', 'string', 'max:5000'],

            // Itinerary
            'itinerary' => ['nullable', 'array'],
            'itinerary.*.title.en' => ['required', 'string', 'max:160'],
            'itinerary.*.title.it' => ['nullable', 'string', 'max:160'],
            'itinerary.*.description.*' => ['nullable', 'string', 'max:5000'],
            'itinerary.*.overnight' => ['nullable', 'string', 'max:160'],
            'itinerary.*.items' => ['nullable', 'array'],
            'itinerary.*.items.*.time_from' => ['nullable', 'date_format:H:i'],
            'itinerary.*.items.*.time_to' => ['nullable', 'date_format:H:i'],
            'itinerary.*.items.*.title.en' => ['required', 'string', 'max:160'],
            'itinerary.*.items.*.title.it' => ['nullable', 'string', 'max:160'],
            'itinerary.*.items.*.description.*' => ['nullable', 'string', 'max:2000'],
            'itinerary.*.items.*.place' => ['nullable', 'string', 'max:160'],

            // Add-ons
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],

            // Nile
            'vessel_name' => ['nullable', 'string', 'max:120'],
            'itinerary_from' => ['nullable', 'string', 'max:120'],
            'itinerary_to' => ['nullable', 'string', 'max:120'],
            'vessel_facilities' => ['nullable', 'array'],
            'vessel_facilities.*' => ['string', 'max:60'],
            'meal_type' => ['nullable', 'string', 'max:40'],

            // SEO
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:170'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('private_enabled') && ! $this->boolean('group_enabled')) {
                    $validator->errors()->add('private_enabled', 'Enable at least one booking type (private or group).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_options.required' => 'Add at least one unit option for a per-unit trip.',
            'private_tiers.required' => 'Add at least one private price tier, or disable private bookings.',
            'base_price.required' => 'Enter the group adult price, or disable group bookings.',
            'max_persons.gte' => 'The maximum persons must be at least the minimum.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title.en' => 'title (English)',
            'unit_options.*.name.en' => 'option name',
            'unit_options.*.seats' => 'seats',
            'unit_options.*.price' => 'retail price',
            'itinerary.*.title.en' => 'day title',
            'itinerary.*.items.*.title.en' => 'timeline item title',
        ];
    }

    /**
     * Validated payload in the shape TripData expects (itinerary => days, Nile fields only for Nile trips).
     *
     * @return array<string, mixed>
     */
    public function tripPayload(): array
    {
        $data = $this->validated();
        $data['featured'] = $this->boolean('featured');
        $data['private_enabled'] = $this->boolean('private_enabled');
        $data['group_enabled'] = $this->boolean('group_enabled');

        if ($this->has('itinerary_present')) {
            $data['itinerary'] = array_values($data['itinerary'] ?? []);
        }

        if ($this->has('addons_present')) {
            $data['addon_ids'] ??= [];
        }

        if ($data['type'] !== TripType::Nile->value) {
            foreach (['vessel_name', 'itinerary_from', 'itinerary_to', 'vessel_facilities', 'meal_type'] as $field) {
                $data[$field] = null;
            }
        }

        if (isset($data['availability']['group']) && ! isset($data['availability']['group']['weekdays'])) {
            $data['availability']['group']['weekdays'] = [];
        }

        if (isset($data['unit_options'])) {
            foreach ($data['unit_options'] as $index => $option) {
                $data['unit_options'][$index]['children_allowed'] = (bool) ($option['children_allowed'] ?? false);
            }
        }

        return $data;
    }

    private function trip(): ?Trip
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip ? $trip : null;
    }
}
