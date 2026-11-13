<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments (SRS 10): online gateways and manual methods, recorded payments and proofs of payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 20);
            $table->string('driver', 30)->nullable();
            $table->json('name');
            $table->json('instructions')->nullable();
            $table->json('account_details')->nullable();
            $table->json('currencies')->nullable();
            $table->boolean('proof_required')->default(true);
            $table->decimal('surcharge_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('deadline_hours')->nullable();
            $table->string('logo')->nullable();
            $table->string('mode', 10)->default('test');
            $table->text('credentials')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('amount_in_currency', 12, 2);
            $table->string('transaction_id')->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20)->default('deposit');
            $table->json('files');
            $table->string('transaction_number', 100)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('sender_name', 120)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
