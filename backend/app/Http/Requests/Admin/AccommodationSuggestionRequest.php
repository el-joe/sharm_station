<?php

namespace App\Http\Requests\Admin;

use App\Enums\BuildingType;
use App\Enums\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Accommodation suggestion (building + rooms) as edited on the review page before approval.
 *
 * The same rules validate the MCP tool "create_accommodation_suggestion" (see payloadRules()), and they
 * mirror BuildingRequest and RoomRequest so an approved suggestion always passes the admin forms.
 */
class AccommodationSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-prices') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $building = (array) $this->input('building', []);
        $building['slug'] = Str::slug((string) (($building['slug'] ?? null) ?: ($building['name']['en'] ?? '')));
        $building['taxes_included'] = filter_var($building['taxes_included'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $building['amenities'] = array_values((array) ($building['amenities'] ?? []));

        $rooms = array_values(array_map(function ($room): array {
            $room = (array) $room;
            $room['amenities'] = array_values((array) ($room['amenities'] ?? []));

            return $room;
        }, (array) $this->input('rooms', [])));

        $this->merge(['building' => $building, 'rooms' => $rooms, 'publish' => $this->boolean('publish')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...self::payloadRules(), 'publish' => ['boolean']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::payloadMessages();
    }

    /**
     * Rules for the normalized payload {building: {...}, rooms: [...], notes}.
     *
     * @return array<string, mixed>
     */
    public static function payloadRules(): array
    {
        return [
            'building' => ['required', 'array'],
            'building.name' => ['required', 'array'],
            'building.name.en' => ['required', 'string', 'max:160'],
            'building.name.it' => ['nullable', 'string', 'max:160'],
            'building.slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('buildings', 'slug')],
            'building.type' => ['required', Rule::enum(BuildingType::class)],
            'building.destination_slug' => ['required', 'string', Rule::exists('destinations', 'slug')->where('is_active', true)],
            'building.address' => ['nullable', 'string', 'max:255'],
            'building.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'building.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'building.stars' => ['nullable', 'integer', 'between:1,5'],
            'building.description' => ['nullable', 'array'],
            'building.description.en' => ['nullable', 'string', 'max:20000'],
            'building.description.it' => ['nullable', 'string', 'max:20000'],
            'building.amenities' => ['array', 'max:40'],
            'building.amenities.*' => ['string', 'max:60'],
            'building.check_in_from' => ['nullable', 'date_format:H:i'],
            'building.check_out_until' => ['nullable', 'date_format:H:i'],
            'building.taxes_included' => ['required', 'boolean'],
            'building.tax_per_night' => ['nullable', 'numeric', 'min:0', 'max:10000', 'required_if:building.taxes_included,false'],
            'building.phone' => ['nullable', 'string', 'max:40'],
            'building.email' => ['nullable', 'email', 'max:255'],

            'rooms' => ['present', 'array', 'max:30'],
            'rooms.*.name' => ['required', 'array'],
            'rooms.*.name.en' => ['required', 'string', 'max:160'],
            'rooms.*.name.it' => ['nullable', 'string', 'max:160'],
            'rooms.*.room_type' => ['required', Rule::enum(RoomType::class)],
            'rooms.*.max_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'rooms.*.max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'rooms.*.max_total' => ['required', 'integer', 'min:1', 'max:30', 'gte:rooms.*.max_adults'],
            'rooms.*.beds' => ['nullable', 'array'],
            'rooms.*.beds.en' => ['nullable', 'string', 'max:120'],
            'rooms.*.beds.it' => ['nullable', 'string', 'max:120'],
            'rooms.*.size_m2' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'rooms.*.base_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'rooms.*.min_nights' => ['required', 'integer', 'min:1', 'max:365'],
            'rooms.*.max_nights' => ['nullable', 'integer', 'gte:rooms.*.min_nights', 'max:365'],
            'rooms.*.amenities' => ['array', 'max:40'],
            'rooms.*.amenities.*' => ['string', 'max:60'],
            'rooms.*.description' => ['nullable', 'array'],
            'rooms.*.description.en' => ['nullable', 'string', 'max:10000'],
            'rooms.*.description.it' => ['nullable', 'string', 'max:10000'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function payloadMessages(): array
    {
        return [
            'building.slug.unique' => 'A building with this name/slug already exists. Check that the accommodation is not already on the site.',
            'building.slug.regex' => 'The slug may contain only lowercase letters, numbers and dashes.',
            'building.destination_slug.exists' => 'Unknown destination: use the slug of an active destination.',
            'building.tax_per_night.required_if' => 'Enter the tax per night, or set "taxes included".',
            'rooms.*.max_total.gte' => 'The total capacity must be at least the maximum adults.',
        ];
    }
}
