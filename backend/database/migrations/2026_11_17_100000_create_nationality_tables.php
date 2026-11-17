<?php

use Database\Seeders\NationalitySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nationality pricing (CR-10): groups of countries; one group is the default for unassigned countries.
 * The ISO country list and the four standard groups are reference data, so they are loaded here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationality_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->json('name');
            $table->foreignId('nationality_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        (new NationalitySeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
        Schema::dropIfExists('nationality_groups');
    }
};
