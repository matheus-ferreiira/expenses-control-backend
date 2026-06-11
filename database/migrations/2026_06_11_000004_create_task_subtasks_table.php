<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title', 500);
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['task_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_subtasks');
    }
};
