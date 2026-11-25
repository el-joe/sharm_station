<?php

namespace App\Repositories\Eloquent;

use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Repositories\Contracts\NotificationTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<NotificationTemplate>
 */
class NotificationTemplateRepository extends BaseRepository implements NotificationTemplateRepositoryInterface
{
    protected function model(): string
    {
        return NotificationTemplate::class;
    }

    public function findFor(NotificationEvent $event, string $locale): ?NotificationTemplate
    {
        return $this->query()->where('event', $event)->where('locale', $locale)->first();
    }

    public function allOrdered(): Collection
    {
        return $this->query()->orderBy('event')->orderBy('locale')->get();
    }
}
