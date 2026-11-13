<?php

namespace App\Services;

use App\DTOs\Newsletter\SubscribeData;
use App\Models\NewsletterSubscriber;
use App\Repositories\Contracts\NewsletterSubscriberRepositoryInterface;

/**
 * Newsletter sign-up. Idempotent: subscribing twice keeps one row (and re-activates an unsubscribed address).
 */
class NewsletterService
{
    public function __construct(private readonly NewsletterSubscriberRepositoryInterface $subscribers) {}

    /**
     * @return array{subscriber: NewsletterSubscriber, created: bool}
     */
    public function subscribe(SubscribeData $data): array
    {
        $existing = $this->subscribers->findByEmail($data->email);

        if ($existing !== null) {
            $this->subscribers->update($existing, [
                'locale' => $data->locale,
                'unsubscribed_at' => null,
                'subscribed_at' => $existing->unsubscribed_at !== null ? now() : $existing->subscribed_at,
            ]);

            return ['subscriber' => $existing, 'created' => false];
        }

        return [
            'subscriber' => $this->subscribers->create([
                'email' => $data->email,
                'locale' => $data->locale,
                'source' => $data->source,
                'subscribed_at' => now(),
            ]),
            'created' => true,
        ];
    }
}
