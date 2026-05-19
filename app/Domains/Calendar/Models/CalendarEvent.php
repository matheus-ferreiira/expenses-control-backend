<?php

namespace App\Domains\Calendar\Models;

use App\Domains\Calendar\Enums\EventSource;
use App\Models\User;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'start_date',
        'end_date',
        'is_all_day',
        'color',
        'source',
        'external_id',
        'recurrence_rule',
        'metadata',
    ];

    protected $casts = [
        'source' => EventSource::class,
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_all_day' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function newFactory(): CalendarEventFactory
    {
        return CalendarEventFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->where('end_date', '>=', now())
            ->where('start_date', '<=', now()->addDays($days));
    }
}
