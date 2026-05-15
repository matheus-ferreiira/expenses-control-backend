<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'category_id']);
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->index(['bank_account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'category_id']);
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropIndex(['bank_account_id', 'user_id']);
        });
    }
};
