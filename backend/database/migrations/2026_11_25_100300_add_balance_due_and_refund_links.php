<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balance due date set by the admin (SRS 10.1) and refunds linked to the payment they pay back (SRS 10.2, 12.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('balance_due_on')->nullable()->after('payment_due_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('parent_payment_id')->nullable()->after('booking_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_payment_id'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('balance_due_on'));
    }
};
