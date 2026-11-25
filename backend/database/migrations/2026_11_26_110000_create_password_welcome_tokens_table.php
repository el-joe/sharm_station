<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens of the "Welcome, set your password" link (password broker "welcome", see config/auth.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_welcome_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_welcome_tokens');
    }
};
