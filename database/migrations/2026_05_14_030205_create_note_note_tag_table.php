<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('note_note_tag', function (Blueprint $table) {
            $table->foreignUuid('note_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('note_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['note_id', 'note_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_note_tag');
    }
};
