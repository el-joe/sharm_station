<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seasons per nationality group (null = all groups). Existing seasons become "all groups".
 * Explicit partner prices are removed (D6): price maps keep only the retail price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_prices', function (Blueprint $table) {
            $table->foreignId('nationality_group_id')->nullable()->after('priceable_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable()->after('price_per_night');
        });

        DB::table('season_prices')->orderBy('id')->each(function (object $season): void {
            $flatten = function (?string $json): ?string {
                $map = $json !== null ? json_decode($json, true) : null;

                if (! is_array($map)) {
                    return $json;
                }

                $clean = [];

                foreach ($map as $id => $value) {
                    $price = is_array($value) ? ($value['price'] ?? null) : $value;

                    if (is_numeric($price)) {
                        $clean[$id] = (float) $price;
                    }
                }

                return $clean === [] ? null : json_encode($clean);
            };

            DB::table('season_prices')->where('id', $season->id)->update([
                'unit_prices' => $flatten($season->unit_prices),
                'tier_prices' => $flatten($season->tier_prices),
            ]);
        });

        Schema::table('season_prices', function (Blueprint $table) {
            $table->dropColumn('partner_price');
        });
    }

    public function down(): void
    {
        Schema::table('season_prices', function (Blueprint $table) {
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->dropConstrainedForeignId('nationality_group_id');
            $table->dropColumn('price');
        });
    }
};
