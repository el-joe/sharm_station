<?php

namespace App\Repositories\Eloquent;

use App\Models\NewsletterSubscriber;
use App\Repositories\Contracts\NewsletterSubscriberRepositoryInterface;

/**
 * @extends BaseRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends BaseRepository implements NewsletterSubscriberRepositoryInterface
{
    protected function model(): string
    {
        return NewsletterSubscriber::class;
    }

    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->query()->where('email', $email)->first();
    }
}
