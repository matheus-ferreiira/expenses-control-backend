<?php

namespace App\Domains\Habits\Models;

use Database\Factories\HabitLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['habit_id', 'completed_date', 'notes'];

    protected $casts = [
        'completed_date' => 'date',
    ];

    protected static function newFactory(): HabitLogFactory
    {
        return HabitLogFactory::new();
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
