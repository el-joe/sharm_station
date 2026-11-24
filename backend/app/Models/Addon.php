<?php

namespace App\Models;

use App\Enums\AddonPricingBasis;
use App\Enums\BookingType;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['name', 'description', 'image', 'price', 'pricing_basis', 'required', 'max_quantity', 'booking_types', 'is_active'])]
class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pricing_basis' => AddonPricingBasis::class,
            'required' => 'boolean',
            'max_quantity' => 'integer',
            'booking_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MorphToMany<Trip, $this>
     */
    public function trips(): MorphToMany
    {
        return $this->morphedByMany(Trip::class, 'assignable', 'addon_assignments');
    }

    /**
     * @return MorphToMany<Package, $this>
     */
    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'assignable', 'addon_assignments');
    }

    public function appliesTo(BookingType $bookingType): bool
    {
        return empty($this->booking_types) || in_array($bookingType->value, $this->booking_types, true);
    }
}
