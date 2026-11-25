<?php

namespace App\Http\Requests\Api;

use App\Enums\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomIndexRequest extends FormRequest
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
            'destination' => ['nullable', 'string', 'max:120'],
            'building' => ['nullable', 'string', 'max:120'],
            'room_type' => ['nullable', Rule::enum(RoomType::class)],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('price_min'), 'gte:price_min')],
            'stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'check_in' => ['nullable', 'required_with:check_out', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['nullable', 'required_with:check_in', 'date_format:Y-m-d', 'after:check_in'],
            'free_cancellation' => ['nullable', 'boolean'],
            'only_available' => ['nullable', 'boolean'],
            'group_by' => ['nullable', Rule::in(['building'])],
            'sort' => ['nullable', Rule::in(['recommended', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
