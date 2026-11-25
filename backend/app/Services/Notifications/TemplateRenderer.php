<?php

namespace App\Services\Notifications;

use App\DTOs\Notification\RenderedNotificationData;
use App\Enums\NotificationEvent;
use Illuminate\Support\Facades\Lang;

/**
 * Renders template subject and body with {{placeholders}}. Every value is HTML-escaped; the template itself is
 * escaped too, so the only markup is the one produced here: paragraphs, line breaks, **bold** and links.
 */
class TemplateRenderer
{
    public const SUPPORTED_LOCALES = ['en', 'it'];

    public static function locale(?string $locale): string
    {
        $locale = strtolower(substr((string) $locale, 0, 2));

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en';
    }

    /**
     * Default subject, body and button text of an event (lang/{locale}/notifications.php).
     *
     * @return array{subject: string, body: string, action: string}
     */
    public function defaults(NotificationEvent $event, string $locale): array
    {
        $default = Lang::get('notifications.events.'.$event->value, [], self::locale($locale));

        return [
            'subject' => (string) ($default['subject'] ?? $event->label()),
            'body' => (string) ($default['body'] ?? ''),
            'action' => (string) ($default['action'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string|int|float|null>  $vars
     */
    public function render(NotificationEvent $event, string $locale, string $subject, string $body, array $vars): RenderedNotificationData
    {
        $vars = array_map(fn ($value): string => (string) ($value ?? ''), $vars);
        $actionPlaceholder = $event->actionPlaceholder();
        $actionUrl = $actionPlaceholder !== null && filter_var($vars[$actionPlaceholder] ?? '', FILTER_VALIDATE_URL) ? $vars[$actionPlaceholder] : null;
        $actionLabel = $this->defaults($event, $locale)['action'];

        return new RenderedNotificationData(
            subject: trim(preg_replace('/\s+/', ' ', $this->replace($subject, $vars, false))),
            html: $this->toHtml($body, $vars),
            text: $this->replace(str_replace('**', '', $body), $vars, false),
            actionLabel: $actionUrl !== null && $actionLabel !== '' ? $actionLabel : null,
            actionUrl: $actionUrl,
        );
    }

    /**
     * Placeholders used in a template that the event does not provide.
     *
     * @return list<string>
     */
    public function unknownPlaceholders(NotificationEvent $event, string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);

        return array_values(array_diff(array_unique($matches[1]), $event->placeholders()));
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function replace(string $text, array $vars, bool $escape): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $match) use ($vars, $escape): string {
            $value = $vars[$match[1]] ?? '';

            return $escape ? e($value) : $value;
        }, $text);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function toHtml(string $body, array $vars): string
    {
        $paragraphs = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", trim($body))) ?: [];

        return collect($paragraphs)
            ->map(function (string $paragraph) use ($vars): string {
                $html = $this->replace(e($paragraph), $vars, true);
                $html = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
                $html = (string) preg_replace('~(?<![="\'])(https?://[^\s<]+)~', '<a href="$1" style="color:#101a5c;">$1</a>', $html);

                return '<p style="margin:0 0 16px;">'.nl2br($html, false).'</p>';
            })
            ->implode('');
    }
}
