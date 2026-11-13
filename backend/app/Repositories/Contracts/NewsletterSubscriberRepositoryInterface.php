<?php

namespace App\Repositories\Contracts;

use App\Models\NewsletterSubscriber;

/**
 * @extends BaseRepositoryInterface<NewsletterSubscriber>
 */
interface NewsletterSubscriberRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?NewsletterSubscriber;
}
