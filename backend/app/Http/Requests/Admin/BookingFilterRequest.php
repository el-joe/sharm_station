<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The export link sends "search"; the DataTables request sends search[value].
        $search = $this->input('search');
        $this->merge(['search' => is_array($search) ? ($search['value'] ?? null) : $search]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'booking_type' => ['nullable', Rule::enum(BookingType::class)],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'partner_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'cancellation' => ['nullable', Rule::in(['pending', 'any'])],
        ];
    }
}
