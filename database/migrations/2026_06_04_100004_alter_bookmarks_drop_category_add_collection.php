<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable bookmark_collection_id
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->foreignUuid('bookmark_collection_id')
                ->nullable()
                ->after('id')
                ->constrained('bookmark_collections')
                ->cascadeOnDelete();
        });

        // 2. Populate from existing category → collection relationship
        DB::statement('
            UPDATE bookmarks b
            JOIN bookmark_categories c ON b.bookmark_category_id = c.id
            SET b.bookmark_collection_id = c.bookmark_collection_id
        ');

        // 3. Drop old FK and column, make new column NOT NULL
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropForeign(['bookmark_category_id']);
            $table->dropColumn('bookmark_category_id');
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->uuid('bookmark_collection_id')->nullable(false)->change();
            $table->index(['bookmark_collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropIndex(['bookmark_collection_id', 'position']);
            $table->dropForeign(['bookmark_collection_id']);
            $table->dropColumn('bookmark_collection_id');
            $table->foreignUuid('bookmark_category_id')
                ->nullable()
                ->after('id')
                ->constrained('bookmark_categories')
                ->cascadeOnDelete();
        });
    }
};
