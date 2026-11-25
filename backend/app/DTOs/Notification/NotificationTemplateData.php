<?php

namespace App\DTOs\Notification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin payload of one template (subject and body with {{placeholders}}).
 */
final readonly class NotificationTemplateData
{
    public function __construct(
        public string $subject,
        public string $body,
        public bool $isEnabled = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            subject: trim((string) $data['subject']),
            body: str_replace("\r\n", "\n", trim((string) $data['body'])),
            isEnabled: (bool) ($data['is_enabled'] ?? false),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array{subject: string, body: string, is_enabled: bool}
     */
    public function toAttributes(): array
    {
        return ['subject' => $this->subject, 'body' => $this->body, 'is_enabled' => $this->isEnabled];
    }
}
