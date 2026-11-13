<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive content fields (unit label, FAQ, what to bring, guide languages, cancellation text, Nile cabins,
 * timeline "transfer included") and the included-transfer tasks created for package bookings (SRS 4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('unit_label')->nullable()->after('pricing_mode');
            $table->json('faq')->nullable()->after('excluded');
            $table->json('what_to_bring')->nullable()->after('faq');
            $table->json('guide_languages')->nullable()->after('what_to_bring');
            $table->json('cancellation_policy')->nullable()->after('guide_languages');
            $table->json('cabins')->nullable()->after('meal_type');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('transfers_included')->default(true)->after('price_mode');
            $table->json('faq')->nullable()->after('excluded');
            $table->json('what_to_bring')->nullable()->after('faq');
            $table->json('guide_languages')->nullable()->after('what_to_bring');
            $table->json('cancellation_policy')->nullable()->after('guide_languages');
            $table->unsignedSmallInteger('sort')->default(0)->after('featured');
        });

        Schema::table('trip_timeline_items', function (Blueprint $table) {
            $table->boolean('transfer_included')->default(false)->after('image');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->unsignedSmallInteger('free_cancellation_hours')->nullable()->after('cancellation_policy');
        });

        Schema::table('google_reviews', function (Blueprint $table) {
            $table->string('author_country', 60)->nullable()->after('author_url');
        });

        Schema::create('booking_included_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_included_transfer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_place');
            $table->string('to_place');
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('vehicle_type', 20)->nullable();
            $table->unsignedSmallInteger('passengers')->default(1);
            $table->string('status', 30)->default('pending');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->string('car_plate', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_included_transfers');

        Schema::table('google_reviews', fn (Blueprint $table) => $table->dropColumn('author_country'));
        Schema::table('buildings', fn (Blueprint $table) => $table->dropColumn('free_cancellation_hours'));
        Schema::table('trip_timeline_items', fn (Blueprint $table) => $table->dropColumn('transfer_included'));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn(['transfers_included', 'faq', 'what_to_bring', 'guide_languages', 'cancellation_policy', 'sort']));
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['unit_label', 'faq', 'what_to_bring', 'guide_languages', 'cancellation_policy', 'cabins']));
    }
};
