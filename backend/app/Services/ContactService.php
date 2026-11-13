<?php

namespace App\Services;

use App\DTOs\Contact\ContactMessageData;
use App\Models\ContactMessage;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ContactService
{
    public function __construct(private readonly ContactMessageRepositoryInterface $messages) {}

    public function submit(ContactMessageData $data): ContactMessage
    {
        return $this->messages->create($data->toAttributes());
    }

    public function markRead(ContactMessage $message): ContactMessage
    {
        return $message->status === 'new' ? $this->messages->update($message, ['status' => 'read']) : $message;
    }

    /**
     * @return Builder<ContactMessage>
     */
    public function adminQuery(?string $status = null): Builder
    {
        return $this->messages->query()->select('contact_messages.*')->when($status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
