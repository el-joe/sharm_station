<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI suggestions created by Claude through the MCP server (CR-14). Nothing is applied until an admin approves.
 * Buildings, rooms and transfer fixed routes gain the same `seo` JSON column trips and packages already have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->nullableMorphs('subject');
            $table->json('payload');
            $table->json('original')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('mcp');
            $table->foreignId('created_by_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        foreach (['buildings', 'rooms', 'transfer_fixed_routes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('seo')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['buildings', 'rooms', 'transfer_fixed_routes'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('seo'));
        }

        Schema::dropIfExists('ai_suggestions');
    }
};
