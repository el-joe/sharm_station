<?php

namespace App\Repositories\Contracts;

use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepositoryInterface<NotificationTemplate>
 */
interface NotificationTemplateRepositoryInterface extends BaseRepositoryInterface
{
    public function findFor(NotificationEvent $event, string $locale): ?NotificationTemplate;

    /**
     * @return Collection<int, NotificationTemplate>
     */
    public function allOrdered(): Collection;
}
