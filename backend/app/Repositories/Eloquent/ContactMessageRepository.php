<?php

namespace App\Repositories\Eloquent;

use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;

/**
 * @extends BaseRepository<ContactMessage>
 */
class ContactMessageRepository extends BaseRepository implements ContactMessageRepositoryInterface
{
    protected function model(): string
    {
        return ContactMessage::class;
    }
}
