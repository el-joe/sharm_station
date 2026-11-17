<?php

namespace Database\Factories;

use App\Models\NationalityGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NationalityGroup>
 */
class NationalityGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' group';

        return [
            'code' => Str::slug($name, '_').'_'.Str::lower(Str::random(4)),
            'name' => ['en' => $name, 'it' => $name],
            'is_default' => false,
            'sort' => 10,
            'is_active' => true,
        ];
    }
}
