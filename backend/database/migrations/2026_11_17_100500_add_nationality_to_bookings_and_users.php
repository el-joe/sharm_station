<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings store the nationality and group used for the price and the commission percentage and its source;
 * users keep their chosen nationality (CR-10, CR-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->char('nationality_code', 2)->nullable()->after('partner_id');
            $table->foreignId('nationality_group_id')->nullable()->after('nationality_code')->constrained()->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable()->after('commission_amount');
        });

        DB::table('bookings')->whereNull('partner_id')->update(['price_rule' => 'none']);
        DB::table('bookings')->whereNotNull('partner_id')->where('price_rule', 'retail')->update(['price_rule' => 'none']);
        DB::table('bookings')->whereNotNull('partner_id')->whereIn('price_rule', ['partner_price', 'category_commission', 'default_commission', 'mixed'])
            ->update(['price_rule' => 'partner_override']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('price_rule', 30)->default('none')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->char('nationality_code', 2)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('nationality_code'));

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nationality_group_id');
            $table->dropColumn(['nationality_code', 'commission_percent']);
        });
    }
};
