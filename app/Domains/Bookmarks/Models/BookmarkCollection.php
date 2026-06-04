<?php

namespace App\Domains\Bookmarks\Models;

use App\Models\User;
use Database\Factories\BookmarkCollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookmarkCollection extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function newFactory(): BookmarkCollectionFactory
    {
        return BookmarkCollectionFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(BookmarkCategory::class);
    }

    public function bookmarks(): HasManyThrough
    {
        return $this->hasManyThrough(Bookmark::class, BookmarkCategory::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('created_at');
    }
}
