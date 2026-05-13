<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing data before changing defaults
        DB::table('tasks')->where('priority', 'medium')->update(['priority' => 'normal']);
        DB::table('tasks')->where('status', 'done')->update(['status' => 'completed']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->change();
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('tasks')->where('priority', 'normal')->update(['priority' => 'medium']);
        DB::table('tasks')->where('status', 'completed')->update(['status' => 'done']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 20)->default('medium')->change();
            $table->string('status', 20)->default('pending')->change();
        });
    }
};
