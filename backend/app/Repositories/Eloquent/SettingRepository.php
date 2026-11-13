<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;

/**
 * @extends BaseRepository<Setting>
 */
class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    protected function model(): string
    {
        return Setting::class;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function many(array $keys): array
    {
        return $this->query()->whereIn('key', $keys)->pluck('value', 'key')->all();
    }
}
