<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_prices', function (Blueprint $table) {
            $table->id();
            $table->morphs('priceable');
            $table->json('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('recurring_yearly')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('adult_price', 10, 2)->nullable();
            $table->decimal('child_price', 10, 2)->nullable();
            $table->decimal('partner_price', 10, 2)->nullable();
            $table->json('unit_prices')->nullable();
            $table->json('tier_prices')->nullable();
            $table->decimal('price_per_night', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['priceable_type', 'priceable_id', 'is_active', 'starts_on'], 'season_prices_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_prices');
    }
};
