<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Booking\AdminBookingData;
use App\Enums\BookingType;
use App\Enums\TravelerType;
use App\Http\Requests\Api\RoomQuoteRequest;
use App\Http\Requests\Api\TripQuoteRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin booking (quote and create): product, party, booker (existing customer, new customer or partner), lead.
 * Travellers are optional: placeholders named after the lead are created when they are left out.
 */
class AdminBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nationality_code'))) {
            $this->merge(['nationality_code' => strtoupper($this->input('nationality_code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isRoom = $this->input('product_type') === 'room';
        $stay = RoomQuoteRequest::stayRules();

        return [
            'product_type' => ['required', Rule::in(AdminBookingData::PRODUCTS)],
            'nationality_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'currency' => ['nullable', 'string', 'size:3'],
            'locale' => ['nullable', Rule::in(['en', 'it'])],
            'booker' => ['required', Rule::in(['customer', 'new_customer', 'partner'])],
            'customer_id' => ['nullable', 'integer'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.name' => ['required_if:booker,new_customer', 'nullable', 'string', 'max:255'],
            'new_customer.email' => ['required_if:booker,new_customer', 'nullable', 'email', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:40'],
            'partner_id' => ['required_if:booker,partner', 'nullable', 'integer'],
            'lead' => ['nullable', 'array'],
            'lead.name' => [Rule::requiredIf(fn (): bool => $this->input('booker') === 'customer' && blank($this->input('customer_id'))), 'nullable', 'string', 'max:255'],
            'lead.email' => [Rule::requiredIf(fn (): bool => $this->input('booker') === 'customer' && blank($this->input('customer_id'))), 'nullable', 'email', 'max:255'],
            'lead.phone' => ['nullable', 'string', 'max:40'],

            'slug' => [Rule::requiredIf(! $isRoom), 'nullable', 'string', 'max:255'],
            'booking_type' => [Rule::requiredIf(! $isRoom), 'nullable', Rule::enum(BookingType::class)],
            'travel_date' => [Rule::requiredIf(! $isRoom), 'nullable', 'date_format:Y-m-d'],
            'adults' => [Rule::requiredIf(! $isRoom), 'nullable', 'integer', 'min:1', 'max:100'],
            'child_ages' => ['nullable', 'array', 'max:50'],
            'child_ages.*' => ['integer', 'min:0', 'max:17'],
            'units' => ['nullable', 'array', 'max:20'],
            'units.*.unit_option_id' => ['required', 'integer'],
            'units.*.quantity' => ['required', 'integer', 'min:0', 'max:100'],
            'addons' => ['nullable', 'array', 'max:30'],
            'addons.*.addon_id' => ['required', 'integer'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...TripQuoteRequest::roomOptionRules(),

            'check_in' => [Rule::requiredIf($isRoom), 'nullable', 'date_format:Y-m-d'],
            'check_out' => [Rule::requiredIf($isRoom), 'nullable', 'date_format:Y-m-d', 'after:check_in'],
            'rooms' => [Rule::requiredIf($isRoom), 'nullable', 'array', 'min:1', 'max:10'],
            'rooms.*.room_id' => $stay['rooms.*.room_id'],
            'rooms.*.adults' => $stay['rooms.*.adults'],
            'rooms.*.children_ages' => $stay['rooms.*.children_ages'],
            'rooms.*.children_ages.*' => $stay['rooms.*.children_ages.*'],
            'rooms.*.extra_beds' => $stay['rooms.*.extra_beds'],
            'rooms.*.cots' => $stay['rooms.*.cots'],

            'travelers' => ['nullable', 'array', 'max:100'],
            'travelers.*.first_name' => ['required', 'string', 'max:100'],
            'travelers.*.last_name' => ['required', 'string', 'max:100'],
            'travelers.*.type' => ['required', Rule::enum(TravelerType::class)],
            'travelers.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notify' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...RoomQuoteRequest::stayMessages(),
            'partner_id.required_if' => 'Choose the partner.',
            'lead.name.required' => 'Enter the lead traveller name, or choose a customer.',
            'lead.email.required' => 'Enter the lead traveller e-mail, or choose a customer.',
        ];
    }
}
