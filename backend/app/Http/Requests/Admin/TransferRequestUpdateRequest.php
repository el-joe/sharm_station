<?php

namespace App\Http\Requests\Admin;

use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Operational follow-up of a transfer request or an included-transfer task: status, driver and car.
 */
class TransferRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TransferStatus::class)],
            'driver_name' => ['nullable', 'string', 'max:120', Rule::requiredIf(in_array($this->input('status'), [TransferStatus::Confirmed->value], true))],
            'driver_phone' => ['nullable', 'string', 'max:40'],
            'car_type' => ['nullable', 'string', 'max:60'],
            'car_plate' => ['nullable', 'string', 'max:30'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForUpdate(): array
    {
        return [...$this->validated(), 'status' => TransferStatus::from($this->validated('status'))];
    }
}
