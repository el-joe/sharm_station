<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_policies', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->boolean('children_allowed')->default(true);
            $table->unsignedTinyInteger('min_child_age')->nullable();
            $table->unsignedTinyInteger('adult_from_age')->default(12);
            $table->unsignedTinyInteger('max_discounted_children')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });

        Schema::create('child_age_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['child_policy_id', 'min_age']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_age_bands');
        Schema::dropIfExists('child_policies');
    }
};
