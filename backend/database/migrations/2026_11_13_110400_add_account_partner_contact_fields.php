<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer account and partner panel fields, booking payment deadline and contact form messages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('newsletter')->default(false)->after('currency');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('company');
            $table->boolean('notify_bookings')->default(true)->after('coupons_allowed');
            $table->boolean('notify_statements')->default(true)->after('notify_bookings');
            $table->boolean('price_list_enabled')->default(true)->after('notify_statements');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('payment_due_at')->nullable()->after('payment_status');
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('subject', 20);
            $table->string('booking_reference', 20)->nullable();
            $table->text('message');
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('payment_due_at'));
        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn(['contact_name', 'notify_bookings', 'notify_statements', 'price_list_enabled']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('newsletter'));
    }
};
