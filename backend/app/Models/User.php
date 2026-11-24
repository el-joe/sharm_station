<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'phone', 'locale', 'currency', 'nationality_code', 'newsletter', 'admin_notes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'newsletter' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<Partner, $this>
     */
    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    /**
     * Deactivated accounts cannot sign in (admin panel, website or partner panel).
     */
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function activePartner(): ?Partner
    {
        if ($this->role !== UserRole::Partner) {
            return null;
        }

        $partner = $this->partner;

        return $partner?->status === PartnerStatus::Active ? $partner : null;
    }
}
