<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfers (SRS 5): hubs with their pricing settings, vehicle types, fixed routes and customer requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_hubs', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('airport');
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('radius_km', 8, 2)->default(50);
            $table->decimal('max_distance_km', 8, 2)->nullable();
            $table->decimal('base_fare', 10, 2)->default(0);
            $table->json('distance_tiers')->nullable();
            $table->decimal('min_fare', 10, 2)->default(0);
            $table->unsignedSmallInteger('waiting_grace_minutes')->default(30);
            $table->decimal('waiting_rate_per_hour', 10, 2)->default(0);
            $table->decimal('night_surcharge', 10, 2)->default(0);
            $table->decimal('fixed_fees', 10, 2)->default(0);
            $table->json('extras')->nullable();
            $table->decimal('return_discount_percent', 5, 2)->default(0);
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('min_notice_hours')->default(12);
            $table->json('vehicle_type_ids')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('category', 20);
            $table->unsignedTinyInteger('capacity');
            $table->unsignedTinyInteger('luggage');
            $table->decimal('multiplier', 5, 2)->default(1);
            $table->decimal('min_fare', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('transfer_fixed_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_hub_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('destination_name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('match_radius_km', 6, 2)->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['transfer_hub_id', 'is_enabled']);
        });

        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_hub_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transfer_fixed_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 20)->default('from_hub');
            $table->string('destination_name');
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->dateTime('pickup_at');
            $table->dateTime('return_at')->nullable();
            $table->boolean('round_trip')->default(false);
            $table->unsignedSmallInteger('passengers');
            $table->unsignedSmallInteger('luggage')->default(0);
            $table->json('extras')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('distance_provider', 30);
            $table->boolean('needs_review')->default(false);
            $table->json('breakdown');
            $table->decimal('price', 10, 2);
            $table->string('status', 30)->default('awaiting_payment');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->string('car_type', 60)->nullable();
            $table->string('car_plate', 30)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'pickup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
        Schema::dropIfExists('transfer_fixed_routes');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('transfer_hubs');
    }
};
