<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan v2.4 phase 2: multi-room accommodation (SRS 6.4, 7.2–7.4), extra beds and cots, optional rooms inside
 * multi-day trips and packages (7.1), iCal export/import per room (6.2) and the booking end date (completed job).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_extra_beds')->default(0)->after('extra_bed_price');
            $table->decimal('cot_price', 10, 2)->nullable()->after('max_extra_beds');
            $table->unsignedTinyInteger('max_cots')->default(0)->after('cot_price');
            $table->string('ical_token', 64)->nullable()->unique()->after('max_cots');
        });

        Schema::create('room_ical_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('url', 2048);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('last_nights')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('room_availability', function (Blueprint $table) {
            $table->foreignId('room_ical_feed_id')->nullable()->after('booking_id')->constrained('room_ical_feeds')->cascadeOnDelete();
        });

        Schema::create('room_options', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->unsignedSmallInteger('stage_position')->nullable();
            $table->string('unit_type', 20)->default('room');
            $table->json('name');
            $table->unsignedTinyInteger('stars')->nullable();
            $table->string('pricing_mode', 20)->default('per_room');
            $table->string('price_period', 20)->default('per_night');
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_total');
            $table->unsignedSmallInteger('max_quantity')->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'is_active']);
        });

        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->foreignId('room_option_id')->nullable()->after('room_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('stage_position')->nullable()->after('room_option_id');
            $table->unsignedTinyInteger('adults')->default(0)->after('pricing_method');
            $table->json('child_ages')->nullable()->after('adults');
            $table->unsignedTinyInteger('extra_beds')->default(0)->after('child_ages');
            $table->unsignedTinyInteger('cots')->default(0)->after('extra_beds');
            $table->decimal('extras_total', 10, 2)->default(0)->after('total');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('check_out');
            $table->index(['status', 'end_date']);
        });

        DB::table('bookings')->whereNotNull('check_out')->update(['end_date' => DB::raw('check_out')]);
        DB::table('bookings')->whereNull('end_date')->whereNotNull('start_date')->update(['end_date' => DB::raw('start_date')]);

        foreach (['trip' => 'trips', 'package' => 'packages'] as $morph => $table) {
            DB::table('bookings')
                ->where('bookable_type', $morph)
                ->whereNotNull('start_date')
                ->update(['end_date' => DB::raw("DATE_ADD(start_date, INTERVAL GREATEST(COALESCE((SELECT p.days FROM {$table} p WHERE p.id = bookings.bookable_id), 1), 1) - 1 DAY)")]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'end_date']);
            $table->dropColumn('end_date');
        });

        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_option_id');
            $table->dropColumn(['stage_position', 'adults', 'child_ages', 'extra_beds', 'cots', 'extras_total']);
        });

        Schema::dropIfExists('room_options');

        Schema::table('room_availability', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_ical_feed_id');
        });

        Schema::dropIfExists('room_ical_feeds');

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['ical_token']);
            $table->dropColumn(['max_extra_beds', 'cot_price', 'max_cots', 'ical_token']);
        });
    }
};
