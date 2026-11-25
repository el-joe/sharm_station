<?php

namespace App\Http\Requests\Admin;

use App\DTOs\Settings\SettingsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'admin_emails' => ['nullable', 'string', 'max:1000'],
            'balance_reminder_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'balance_due_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'hold_warning_minutes' => ['nullable', 'integer', 'min:1', 'max:720'],
            'exchange_rate_refresh_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'require_two_factor' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return ['admin_emails.max' => 'Enter at most 1000 characters of admin email addresses.'];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (SettingsData::parseEmails((string) $this->input('admin_emails', '')) as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add('admin_emails', "“{$email}” is not a valid email address.");
                }
            }
        }];
    }
}
