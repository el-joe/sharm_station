<?php

namespace App\DTOs\Notification;

/**
 * An email ready to send: subject, HTML and plain-text bodies and the optional button.
 */
final readonly class RenderedNotificationData
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
    ) {}
}
