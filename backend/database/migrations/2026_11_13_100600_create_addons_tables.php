<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->string('pricing_basis', 20);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('max_quantity')->default(1);
            $table->json('booking_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addon_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->morphs('assignable');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['addon_id', 'assignable_type', 'assignable_id'], 'addon_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_assignments');
        Schema::dropIfExists('addons');
    }
};
