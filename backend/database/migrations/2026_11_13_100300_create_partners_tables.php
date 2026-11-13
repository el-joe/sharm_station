<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company');
            $table->string('tax_number', 64)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('default_commission', 5, 2)->nullable();
            $table->boolean('show_retail_price')->default(false);
            $table->boolean('coupons_allowed')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_category_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->decimal('commission_percent', 5, 2);
            $table->timestamps();

            $table->unique(['partner_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_category_commissions');
        Schema::dropIfExists('partners');
    }
};
