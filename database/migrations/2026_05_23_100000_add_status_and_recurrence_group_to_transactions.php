<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 'confirmed' = transaction happened, affects balance
            // 'pending'   = future recurring occurrence, does NOT affect balance
            $table->string('status', 20)->default('confirmed')->after('is_recurring');
            $table->uuid('recurrence_group_id')->nullable()->after('installment_group_id');
            $table->index('recurrence_group_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group_id']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['status', 'recurrence_group_id']);
        });
    }
};
