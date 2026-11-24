<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website content managed from the admin (SRS 14.1 Content): static pages, blog posts and FAQ.
 * Translatable columns are JSON {"en": ..., "it": ...}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->json('title');
            $table->json('intro')->nullable();
            $table->json('body')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('author')->nullable();
            $table->json('tags')->nullable();
            $table->json('related')->nullable();
            $table->json('seo')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->timestamps();
        });

        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('title');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_category_id')->constrained()->cascadeOnDelete();
            $table->json('question');
            $table->json('answer');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('faq_categories');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('pages');
    }
};
