<?php

namespace App\Domains\Bookmarks\Models;

use App\Models\User;
use Database\Factories\BookmarkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bookmark extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'bookmark_collection_id',
        'user_id',
        'title',
        'url',
        'description',
        'is_favorite',
        'position',
    ];

    protected $attributes = [
        'position' => 0,
        'is_favorite' => false,
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'position' => 'integer',
    ];

    protected static function newFactory(): BookmarkFactory
    {
        return BookmarkFactory::new();
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookmarkCollection::class, 'bookmark_collection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCollection(Builder $query, string $collectionId): Builder
    {
        return $query->where('bookmark_collection_id', $collectionId);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('created_at');
    }
}
