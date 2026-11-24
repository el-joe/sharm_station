<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:120'],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_phone' => ['nullable', 'string', 'max:40'],
            'site_whatsapp' => ['nullable', 'string', 'max:40'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'site_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:site_lng'],
            'site_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:site_lat'],
            'default_deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'hold_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'google_place_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-:]+$/'],
            'preview_commission' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
