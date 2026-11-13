<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('hotel');
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('stars')->nullable();
            $table->json('description')->nullable();
            $table->json('amenities')->nullable();
            $table->string('cover_image')->nullable();
            $table->time('check_in_from')->nullable();
            $table->time('check_out_until')->nullable();
            $table->decimal('deposit_percent', 5, 2)->default(30);
            $table->json('cancellation_policy')->nullable();
            $table->boolean('taxes_included')->default(true);
            $table->decimal('tax_per_night', 10, 2)->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('featured')->default(false);
            $table->timestamps();

            $table->index(['destination_id', 'status']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('number', 20)->nullable();
            $table->string('room_type', 20);
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_total');
            $table->json('beds')->nullable();
            $table->unsignedSmallInteger('size_m2')->nullable();
            $table->json('view')->nullable();
            $table->json('description')->nullable();
            $table->json('amenities')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('pricing_mode', 20)->default('per_room');
            $table->decimal('base_price', 10, 2);
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->decimal('extra_bed_price', 10, 2)->nullable();
            $table->decimal('from_price', 10, 2)->nullable()->index();
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('max_nights')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['building_id', 'status']);
            $table->index(['room_type', 'status']);
        });

        Schema::create('room_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_availability');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
    }
};
