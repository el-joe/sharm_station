<?php

namespace App\Models;

use App\Enums\ContactSubject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'subject', 'booking_reference', 'message', 'locale', 'status'])]
class ContactMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['subject' => ContactSubject::class];
    }
}
