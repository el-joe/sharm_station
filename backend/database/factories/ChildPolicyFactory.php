<?php

namespace Database\Factories;

use App\Models\ChildPolicy;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildPolicy>
 */
class ChildPolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => 'trip',
            'owner_id' => Trip::factory(),
            'children_allowed' => true,
            'adult_from_age' => 12,
        ];
    }
}
