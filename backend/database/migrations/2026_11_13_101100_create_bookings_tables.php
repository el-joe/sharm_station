<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('access_token', 64);
            $table->string('service_type', 20);
            $table->string('booking_type', 20)->nullable();
            $table->string('pricing_mode', 20)->nullable();
            $table->nullableMorphs('bookable');
            $table->string('season_name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_name');
            $table->string('lead_email');
            $table->string('lead_phone', 40)->nullable();
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->decimal('retail_total', 10, 2);
            $table->decimal('partner_total', 10, 2)->nullable();
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->string('price_rule', 30)->default('retail');
            $table->decimal('total', 10, 2);
            $table->decimal('deposit_percent', 5, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('total_in_currency', 12, 2);
            $table->string('status', 30)->default('awaiting_payment');
            $table->string('payment_status', 30)->default('awaiting_payment');
            $table->string('locale', 5)->default('en');
            $table->text('notes')->nullable();
            $table->json('price_lines')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('payment_status');
            $table->index('lead_email');
            $table->index(['service_type', 'start_date']);
        });

        Schema::create('booking_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('type', 10);
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('passport_number', 40)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_unit_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('seats');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('partner_unit_price', 10, 2)->nullable();
            $table->decimal('partner_total', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_name');
            $table->string('room_number', 20)->nullable();
            $table->string('pricing_method', 20);
            $table->unsignedSmallInteger('nights');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('partner_total', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('booking_room_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_traveler_id')->constrained()->cascadeOnDelete();
            $table->decimal('guest_price', 10, 2)->nullable();
            $table->decimal('discount_applied', 10, 2)->nullable();
            $table->decimal('extra_bed_fee', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['booking_room_id', 'booking_traveler_id'], 'booking_room_guests_unique');
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pricing_basis', 20);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('partner_total', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('room_availability', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_availability', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::dropIfExists('booking_status_logs');
        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('booking_room_guests');
        Schema::dropIfExists('booking_rooms');
        Schema::dropIfExists('booking_units');
        Schema::dropIfExists('booking_travelers');
        Schema::dropIfExists('bookings');
    }
};
