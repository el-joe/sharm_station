<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base price of a product per nationality group (CR-10). Columns mirror season_prices so both
 * layers resolve the same way (PriceResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->morphs('priceable');
            $table->foreignId('nationality_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('adult_price', 10, 2)->nullable();
            $table->decimal('child_price', 10, 2)->nullable();
            $table->json('tier_prices')->nullable();
            $table->json('unit_prices')->nullable();
            $table->decimal('price_per_night', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['priceable_type', 'priceable_id', 'nationality_group_id'], 'product_prices_priceable_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
