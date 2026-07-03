<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Which credit card statement a payment settles ("YYYY-MM" = month the cycle closes).
            // Only set on statement-payment transfers; null for everything else.
            $table->string('statement_month', 7)->nullable()->after('card_id');
            $table->index(['card_id', 'statement_month']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['card_id', 'statement_month']);
            $table->dropColumn('statement_month');
        });
    }
};
