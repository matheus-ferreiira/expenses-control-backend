<?php

namespace App\Domains\BankSync\Models;

use App\Domains\BankSync\Services\BankInstitutions;
use App\Models\User;
use Database\Factories\BankConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bank authorised in MeuPluggy and shared with VaultOS (a Pluggy "item"). Disconnecting deletes it,
 * its accounts and its transactions: they are a copy of the bank's data, not the user's records.
 */
class BankConnection extends Model
{
    use HasFactory, HasUuids;

    /** Item statuses that need the user to authorise again in the widget (NOT_FOUND: Pluggy no longer has it). */
    public const NEEDS_ACTION = ['LOGIN_ERROR', 'WAITING_USER_INPUT', 'NOT_FOUND'];

    protected $fillable = [
        'user_id', 'provider', 'external_item_id', 'name', 'connector_id', 'connector_name', 'connector_image_url',
        'connector_color', 'status', 'execution_status', 'consent_expires_at', 'provider_updated_at',
        'last_synced_at', 'last_sync_error',
    ];

    protected $hidden = ['user_id'];

    protected $casts = [
        'connector_id' => 'integer',
        'consent_expires_at' => 'datetime',
        'provider_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected static function newFactory(): BankConnectionFactory
    {
        return BankConnectionFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(BankConnectionAccount::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** The user's name, else the bank behind MeuPluggy (by an account's COMPE code), else Pluggy's connector name. */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }
        if ($this->relationLoaded('accounts')) {
            foreach ($this->accounts as $account) {
                if ($bank = BankInstitutions::name($account->bank_code)) {
                    return $bank;
                }
            }
        }

        return $this->connector_name;
    }

    public function needsAction(): bool
    {
        return in_array($this->status, self::NEEDS_ACTION, true)
            || ($this->consent_expires_at !== null && $this->consent_expires_at->isPast());
    }
}
