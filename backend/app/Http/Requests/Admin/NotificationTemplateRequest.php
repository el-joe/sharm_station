<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationEvent;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Template edit, preview and "send test to me": only placeholders of the event are allowed.
 */
class NotificationTemplateRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->route('event');
            $event = $event instanceof NotificationEvent ? $event : NotificationEvent::tryFrom((string) $event);

            if ($event === null) {
                return;
            }

            $notifications = app(NotificationService::class);

            foreach (['subject', 'body'] as $field) {
                $unknown = $notifications->unknownPlaceholders($event, (string) $this->input($field, ''));

                if ($unknown !== []) {
                    $validator->errors()->add($field, 'Unknown placeholder: {{'.implode('}}, {{', $unknown).'}}.');
                }
            }
        }];
    }
}
