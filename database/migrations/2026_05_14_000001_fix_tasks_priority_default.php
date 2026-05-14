<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix rows with invalid 'medium' priority (migrated before the enum was corrected)
        DB::table('tasks')
            ->where('priority', 'medium')
            ->update(['priority' => 'normal']);

        // Change the column default to match the TaskPriority enum
        DB::statement("ALTER TABLE tasks ALTER COLUMN priority SET DEFAULT 'normal'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tasks ALTER COLUMN priority SET DEFAULT 'medium'");
    }
};
