<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('days');
            $table->unsignedSmallInteger('nights');
            $table->boolean('private_enabled')->default(true);
            $table->boolean('group_enabled')->default(false);
            $table->string('pricing_mode', 20)->default('per_person');
            $table->unsignedSmallInteger('min_persons')->default(1);
            $table->unsignedSmallInteger('max_persons')->nullable();
            $table->string('price_mode', 20)->default('fixed');
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('base_child_price', 10, 2)->nullable();
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('from_price', 10, 2)->nullable()->index();
            $table->decimal('deposit_percent', 5, 2)->default(30);
            $table->unsignedSmallInteger('free_cancellation_hours')->nullable();
            $table->json('highlights')->nullable();
            $table->json('included')->nullable();
            $table->json('excluded')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('seo')->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamps();

            $table->index(['status', 'featured']);
        });

        Schema::create('package_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('days');
            $table->unsignedSmallInteger('nights');
            $table->json('accommodation')->nullable();
            $table->timestamps();

            $table->index(['package_id', 'position']);
        });

        Schema::create('package_stage_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['package_stage_id', 'day_number']);
        });

        Schema::create('package_included_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_place');
            $table->string('to_place');
            $table->unsignedSmallInteger('day_number');
            $table->time('time')->nullable();
            $table->string('vehicle_type', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_included_transfers');
        Schema::dropIfExists('package_stage_trips');
        Schema::dropIfExists('package_stages');
        Schema::dropIfExists('packages');
    }
};
