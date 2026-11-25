<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email notifications (SRS 14.1, 15.1): editable templates per event and language, and the sending log.
 * notification_logs.dedupe_key marks emails sent by scheduled jobs so they are never sent twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->string('locale', 5);
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['event', 'locale']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60)->index();
            $table->string('locale', 5)->default('en');
            $table->string('recipient');
            $table->string('subject');
            $table->string('status', 20)->default('queued')->index();
            $table->text('error')->nullable();
            $table->nullableMorphs('related');
            $table->json('context')->nullable();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->foreignId('resent_from_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
