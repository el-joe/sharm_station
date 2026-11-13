<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('place_id');
            $table->string('google_review_id');
            $table->string('author_name');
            $table->text('author_photo_url')->nullable();
            $table->text('author_url')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('text')->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'google_review_id']);
            $table->index(['place_id', 'is_hidden', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_reviews');
    }
};
