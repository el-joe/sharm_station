<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit partner prices are removed (D6, CR-12): partner price = customer price − commission %.
 */
return new class extends Migration
{
    private const TABLES = ['trips', 'trip_unit_options', 'trip_private_tiers', 'addons', 'packages', 'rooms', 'transfer_fixed_routes'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'partner_price')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('partner_price'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->decimal('partner_price', 10, 2)->nullable());
        }
    }
};
