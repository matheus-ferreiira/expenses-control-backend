<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('task_list_id')->nullable()->after('user_id')
                ->constrained('task_lists')->nullOnDelete();
            $table->integer('estimated_minutes')->nullable()->after('is_archived');
            $table->timestamp('next_occurrence_date')->nullable()->after('recurrence_config');

            $table->index(['user_id', 'task_list_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['task_list_id']);
            $table->dropIndex(['user_id', 'task_list_id']);
            $table->dropColumn(['task_list_id', 'estimated_minutes', 'next_occurrence_date']);
        });
    }
};
