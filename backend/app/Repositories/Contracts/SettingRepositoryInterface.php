<?php

namespace App\Repositories\Contracts;

use App\Models\Setting;

/**
 * @extends BaseRepositoryInterface<Setting>
 */
interface SettingRepositoryInterface extends BaseRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array;
}
