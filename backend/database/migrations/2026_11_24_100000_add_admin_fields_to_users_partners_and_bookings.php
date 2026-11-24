<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.3: staff and customer activation with internal notes, partner rejection with a reason,
 * and cancellation requests on bookings (decided by the admin with a suggested refund).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
            $table->text('admin_notes')->nullable()->after('newsletter');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancellation_requested_at')->nullable()->index();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancellation_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_decision', 20)->nullable();
            $table->timestamp('cancellation_decided_at')->nullable();
            $table->text('cancellation_decision_note')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancellation_requested_by');
            $table->dropIndex(['cancellation_requested_at']);
            $table->dropColumn(['cancellation_requested_at', 'cancellation_reason', 'cancellation_decision', 'cancellation_decided_at', 'cancellation_decision_note', 'refund_amount']);
        });

        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn(['rejection_reason', 'rejected_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'admin_notes']));
    }
};
