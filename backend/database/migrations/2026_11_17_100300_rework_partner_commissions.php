<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commissions are percentages per type (CR-11): global settings + optional partner overrides.
 *
 * Old partner rows are mapped to the new types: "trips" → single_day_trips + multi_day_trips,
 * "nile_trips"/"packages"/"transfers" unchanged, "accommodation" dropped (no commission).
 * A partner's old default commission becomes an override for every type it had no explicit rate for,
 * so the effective rate of existing partners does not change.
 */
return new class extends Migration
{
    private const TYPES = ['single_day_trips', 'multi_day_trips', 'nile_trips', 'packages', 'transfers'];

    private const DEFAULTS = ['single_day_trips' => 15, 'multi_day_trips' => 10, 'nile_trips' => 10, 'packages' => 10, 'transfers' => 8];

    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->string('category', 30)->primary();
            $table->decimal('percent', 5, 2)->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('commission_settings')->insert(array_map(
            fn (string $type): array => ['category' => $type, 'percent' => self::DEFAULTS[$type], 'created_at' => $now, 'updated_at' => $now],
            self::TYPES,
        ));

        foreach (DB::table('partner_category_commissions')->where('category', 'trips')->get() as $row) {
            foreach (['single_day_trips', 'multi_day_trips'] as $type) {
                DB::table('partner_category_commissions')->updateOrInsert(
                    ['partner_id' => $row->partner_id, 'category' => $type],
                    ['commission_percent' => $row->commission_percent, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        DB::table('partner_category_commissions')->whereNotIn('category', self::TYPES)->delete();

        foreach (DB::table('partners')->whereNotNull('default_commission')->get(['id', 'default_commission']) as $partner) {
            foreach (self::TYPES as $type) {
                $exists = DB::table('partner_category_commissions')->where('partner_id', $partner->id)->where('category', $type)->exists();

                if (! $exists) {
                    DB::table('partner_category_commissions')->insert([
                        'partner_id' => $partner->id,
                        'category' => $type,
                        'commission_percent' => $partner->default_commission,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('default_commission');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('default_commission', 5, 2)->nullable();
        });

        Schema::dropIfExists('commission_settings');
    }
};
