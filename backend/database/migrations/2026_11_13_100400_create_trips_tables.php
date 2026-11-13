<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('category', 40)->nullable();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->unsignedSmallInteger('days')->default(1);
            $table->unsignedSmallInteger('nights')->default(0);
            $table->boolean('private_enabled')->default(true);
            $table->boolean('group_enabled')->default(false);
            $table->string('pricing_mode', 20)->default('per_person');
            $table->unsignedSmallInteger('min_persons')->default(1);
            $table->unsignedSmallInteger('max_persons')->nullable();
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('base_child_price', 10, 2)->nullable();
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->decimal('from_price', 10, 2)->nullable()->index();
            $table->decimal('deposit_percent', 5, 2)->default(30);
            $table->unsignedSmallInteger('free_cancellation_hours')->nullable();
            $table->json('highlights')->nullable();
            $table->json('included')->nullable();
            $table->json('excluded')->nullable();
            $table->json('meeting_point')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('itinerary_from')->nullable();
            $table->string('itinerary_to')->nullable();
            $table->json('vessel_facilities')->nullable();
            $table->string('meal_type', 40)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('seo')->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['destination_id', 'status']);
            $table->index(['status', 'featured']);
        });

        Schema::create('trip_unit_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedTinyInteger('seats');
            $table->decimal('price', 10, 2);
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('max_quantity')->default(10);
            $table->boolean('children_allowed')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('trip_private_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_people');
            $table->unsignedSmallInteger('max_people')->nullable();
            $table->decimal('price_per_person', 10, 2);
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'min_people']);
        });

        Schema::create('trip_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('booking_type', 20);
            $table->json('weekdays')->nullable();
            $table->json('dates')->nullable();
            $table->json('blackout_dates')->nullable();
            $table->unsignedSmallInteger('lead_time_hours')->default(24);
            $table->timestamps();

            $table->unique(['trip_id', 'booking_type']);
        });

        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('meals')->nullable();
            $table->string('overnight')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'day_number']);
        });

        Schema::create('trip_timeline_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained()->cascadeOnDelete();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('place')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['trip_day_id', 'sort']);
        });

        Schema::create('trip_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->json('alt')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_media');
        Schema::dropIfExists('trip_timeline_items');
        Schema::dropIfExists('trip_days');
        Schema::dropIfExists('trip_availability_rules');
        Schema::dropIfExists('trip_private_tiers');
        Schema::dropIfExists('trip_unit_options');
        Schema::dropIfExists('trips');
    }
};
