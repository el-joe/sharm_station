<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * On-demand revalidation of the Next.js website after admin content changes.
 *
 * POSTs {"tags": [...]} to services.frontend.revalidate_url with the x-revalidate-secret header,
 * after the response is sent and with a short timeout: a website that is down never breaks an admin save.
 */
class WebsiteRevalidator
{
    public const HEADER = 'x-revalidate-secret';

    /**
     * @return list<string>
     */
    public static function pageTags(string $slug): array
    {
        return ['content', 'pages', "page:{$slug}"];
    }

    /**
     * @return list<string>
     */
    public static function postTags(string ...$slugs): array
    {
        return ['content', 'blog', ...array_map(fn (string $slug): string => "blog:{$slug}", array_values(array_unique(array_filter($slugs))))];
    }

    /**
     * @return list<string>
     */
    public static function faqTags(): array
    {
        return ['content', 'faqs'];
    }

    /**
     * Queues the call for after the response.
     *
     * @param  list<string>  $tags
     */
    public function revalidate(array $tags): void
    {
        if (! $this->isConfigured() || $tags === []) {
            return;
        }

        $tags = array_values(array_unique($tags));

        defer(fn () => $this->send($tags), 'website-revalidate:'.implode(',', $tags));
    }

    /**
     * Sends the request now. Failures are logged, never thrown.
     *
     * @param  list<string>  $tags
     */
    public function send(array $tags): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $timeout = (int) config('services.frontend.revalidate_timeout', 2);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->acceptJson()
                ->withHeaders([self::HEADER => (string) config('services.frontend.revalidate_secret')])
                ->post((string) config('services.frontend.revalidate_url'), ['tags' => $tags]);

            if ($response->failed()) {
                Log::warning('Website revalidation failed.', ['status' => $response->status(), 'tags' => $tags]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Website revalidation failed.', ['error' => $exception->getMessage(), 'tags' => $tags]);

            return false;
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.frontend.revalidate_url')) && filled(config('services.frontend.revalidate_secret'));
    }
}
