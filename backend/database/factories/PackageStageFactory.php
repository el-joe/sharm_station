<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Package;
use App\Models\PackageStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageStage>
 */
class PackageStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'destination_id' => Destination::factory(),
            'position' => 1,
            'days' => 3,
            'nights' => 3,
        ];
    }
}
