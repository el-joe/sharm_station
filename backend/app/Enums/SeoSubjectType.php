<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Building;
use App\Models\Package;
use App\Models\Room;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Model;

/**
 * Products an SEO suggestion can target (MCP tool "suggest_seo").
 */
enum SeoSubjectType: string
{
    use HasOptions;

    case Trip = 'trip';
    case Package = 'package';
    case TransferRoute = 'transfer_route';
    case Building = 'building';
    case Room = 'room';

    public function label(): string
    {
        return match ($this) {
            self::Trip => 'Trip',
            self::Package => 'Package',
            self::TransferRoute => 'Transfer route',
            self::Building => 'Building',
            self::Room => 'Room',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Trip => Trip::class,
            self::Package => Package::class,
            self::TransferRoute => TransferFixedRoute::class,
            self::Building => Building::class,
            self::Room => Room::class,
        };
    }

    /**
     * Whether the subject has its own public URL slug (rooms and transfer routes do not).
     */
    public function hasSlug(): bool
    {
        return in_array($this, [self::Trip, self::Package, self::Building], true);
    }

    public static function fromModel(Model $model): self
    {
        return match (true) {
            $model instanceof Trip => self::Trip,
            $model instanceof Package => self::Package,
            $model instanceof TransferFixedRoute => self::TransferRoute,
            $model instanceof Building => self::Building,
            default => self::Room,
        };
    }
}
