<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Sem horário" deixa de ser inferido por meia-noite — flag explícita.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('has_due_time')->default(false)->after('due_date');
        });

        DB::table('tasks')
            ->whereNotNull('due_date')
            ->whereRaw("TIME(due_date) != '00:00:00'")
            ->update(['has_due_time' => true]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('has_due_time');
        });
    }
};
