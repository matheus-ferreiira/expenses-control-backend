<?php

namespace App\Domains\BankSync\Services;

use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Domains\BankSync\Models\BankConnectionTransaction as BankTx;
use App\Domains\BankSync\Models\BankReviewRule;
use App\Domains\Finance\Actions\PayCardStatementAction;
use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Services\CardCycle;
use App\Domains\Finance\Services\FinancialEffects;
use App\Domains\Finance\Services\TransactionService;
use App\Domains\Finance\Services\UserClock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reconciles what the bank reports with the ledger. Nothing reaches the ledger without the user's approval:
 *  - a bank transaction that is already in the ledger (same side, same value, close date) is only linked (matched);
 *  - the rest, from the account's start date on, waits in "A lançar" (new) with a suggestion;
 *  - approving creates the ledger record through the same actions as the app (or confirms a forecast).
 */
class BankReconciler
{
    /** Same value within this many days: the same cash movement */
    public const MATCH_DAYS = 3;

    /** Card installments: the bank dates them by the statement, the ledger by the purchase */
    public const INSTALLMENT_DAYS = 45;

    /** A forecast (pending bill, salary) this close in date and value is suggested for confirmation */
    public const FORECAST_DAYS = 10;

    /** Value difference accepted for a forecast paid by Pix/transfer (rent, salary) */
    public const FORECAST_TOLERANCE = 0.15;

    /** Bills paid as a boleto ("Pagamento efetuado|ENEL…") vary more month to month */
    public const BILL_TOLERANCE = 0.40;

    /** Days with movement, all of them already in the ledger, that show where the app and the bank agree */
    private const SOLID_DAYS = 5;

    private const LOOKBACK_DAYS = 180;

    public const ACTIONS = ['expense', 'income', 'transfer', 'pay_card', 'confirm', 'match', 'ignore'];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly PayCardStatementAction $payCard,
    ) {}

    // ── Linking ────────────────────────────────────────────────────────────────

    /** Links (or unlinks, with null) a bank account to a ledger account/card and reconciles it. */
    public function link(BankConnectionAccount $account, ?string $ledgerId, ?string $importFrom): BankConnectionAccount
    {
        $column = $account->isCredit() ? 'credit_card_id' : 'bank_account_id';
        if ($ledgerId !== null && BankConnectionAccount::where('user_id', $account->user_id)->where($column, $ledgerId)->whereKeyNot($account->id)->exists()) {
            throw ValidationException::withMessages([$column => 'Outra conta do banco já está ligada a esta. Desligue a outra primeiro.']);
        }

        DB::transaction(function () use ($account, $column, $ledgerId, $importFrom) {
            $changed = $account->{$column} !== $ledgerId;
            $account->forceFill([$column => $ledgerId, 'import_from' => $ledgerId === null ? null : $importFrom])->save();
            if ($ledgerId === null || $changed) {
                // Decisions taken against another ledger account do not carry over
                $account->transactions()->where(fn ($q) => $q->whereNull('review_status')->orWhereIn('review_status', [BankTx::NEW, BankTx::BEFORE_START]))
                    ->update(['review_status' => null, 'transaction_id' => null, 'reviewed_at' => null]);
            }
            if ($changed) {
                $account->transactions()->where('review_status', BankTx::MATCHED)
                    ->update(['review_status' => null, 'transaction_id' => null, 'reviewed_at' => null]);
            }
        });

        if ($ledgerId !== null) {
            if ($importFrom === null) {
                $account->forceFill(['import_from' => $this->detectStart($account)])->save();
            }
            $this->reconcile($account);
        }

        return $account->refresh();
    }

    /**
     * Where the app and the bank stop agreeing: the first day with something missing after the latest
     * stretch of SOLID_DAYS days whose movements are all in the ledger. Nothing missing: the day after the
     * latest movement. No such stretch: the first day of the month.
     */
    public function detectStart(BankConnectionAccount $account): string
    {
        $today = UserClock::today($account->user_id);
        $bank = $account->transactions()
            ->whereBetween('date', [Carbon::parse($today)->subDays(self::LOOKBACK_DAYS)->toDateString(), $today])
            ->orderBy('date')->get();
        $open = $bank->reject(fn (BankTx $t) => in_array($t->review_status, [...BankTx::LINKED, BankTx::IGNORED], true));
        $matched = $this->match($account, $open);

        $streak = 0;
        $solidEnd = null;
        $firstGapAfter = null;
        foreach ($bank->groupBy(fn (BankTx $t) => $t->date->toDateString()) as $day => $rows) {
            $ok = $rows->every(fn (BankTx $t) => isset($matched[$t->id]) || in_array($t->review_status, [...BankTx::LINKED, BankTx::IGNORED], true));
            if ($ok) {
                $streak++;
                if ($streak >= self::SOLID_DAYS) {
                    $solidEnd = $day;
                    $firstGapAfter = null;
                }
            } else {
                $streak = 0;
                $firstGapAfter ??= $solidEnd !== null ? $day : null;
            }
        }

        if ($solidEnd === null) {
            return Carbon::parse($today)->startOfMonth()->toDateString();
        }

        return $firstGapAfter ?? Carbon::parse($bank->last()->date)->addDay()->toDateString();
    }

    // ── Reconciliation ─────────────────────────────────────────────────────────

    /** Classifies every undecided bank transaction of a linked account: matched, before_start, new or not due. */
    public function reconcile(BankConnectionAccount $account): void
    {
        if (! $account->isLinked()) {
            return;
        }

        DB::transaction(function () use ($account) {
            BankConnectionAccount::whereKey($account->id)->lockForUpdate()->first();
            $today = UserClock::today($account->user_id);
            $start = $account->import_from?->toDateString() ?? $today;

            // A ledger record deleted by the user frees its bank transaction for review again
            $account->transactions()->whereIn('review_status', BankTx::LINKED)
                ->where(fn ($q) => $q->whereNull('transaction_id')->orWhereHas('transaction', fn ($t) => $t->whereNotNull('deleted_at')))
                ->update(['review_status' => null, 'transaction_id' => null, 'reviewed_at' => null]);

            $open = $account->transactions()
                ->where(fn ($q) => $q->whereNull('review_status')->orWhereIn('review_status', [BankTx::NEW, BankTx::BEFORE_START]))
                ->get();
            $matches = $this->match($account, $open->filter(fn (BankTx $t) => $t->date->toDateString() <= $today));

            foreach ($open as $t) {
                $date = $t->date->toDateString();
                [$status, $ledgerId] = match (true) {
                    isset($matches[$t->id]) => [BankTx::MATCHED, $matches[$t->id]],
                    $date > $today => [null, null],
                    $date < $start => [BankTx::BEFORE_START, null],
                    default => [BankTx::NEW, null],
                };
                if ($t->review_status !== $status || $t->transaction_id !== $ledgerId) {
                    $t->forceFill(['review_status' => $status, 'transaction_id' => $ledgerId, 'reviewed_at' => $ledgerId ? now() : null])->save();
                }
            }
        });
    }

    public function reconcileUser(string $userId): void
    {
        BankConnectionAccount::where('user_id', $userId)
            ->where(fn ($q) => $q->whereNotNull('bank_account_id')->orWhereNotNull('credit_card_id'))
            ->get()->each(fn (BankConnectionAccount $a) => $this->reconcile($a));
    }

    /**
     * Pairs bank transactions with ledger transactions on the same side (account or card), same value
     * and close date (installments: same number within INSTALLMENT_DAYS). On a bank account a pending
     * ledger row is a forecast: it is confirmed by the user, never matched silently. On a card, pending
     * rows are scheduled charges that realize by themselves, so they match.
     *
     * Names decide between equal values: records that name the counterpart ("Reembolso Peterson" for a Pix
     * from PETERSON) are paired first, single records and then two records paid in one Pix (a friend sending
     * two lunches at once); only then the rest, and a record naming another counterpart of the statement is
     * never given to someone else.
     *
     * @param  Collection<int, BankTx>  $bank
     * @return array<string, string> bank transaction id => ledger transaction id
     */
    private function match(BankConnectionAccount $account, Collection $bank): array
    {
        if ($bank->isEmpty()) {
            return [];
        }
        $taken = BankTx::where('bank_connection_account_id', $account->id)->whereIn('review_status', BankTx::LINKED)
            ->whereNotNull('transaction_id')->pluck('transaction_id')->flip();
        $from = Carbon::parse($bank->min('date'))->subDays(self::INSTALLMENT_DAYS)->toDateString();
        $to = Carbon::parse($bank->max('date'))->addDays(self::INSTALLMENT_DAYS)->toDateString();

        $ledgers = [];
        foreach ($this->ledgerSide($account)->whereBetween('transaction_date', [$from, $to])->get() as $ledger) {
            $direction = $this->ledgerDirection($account, $ledger);
            if ($direction === null || isset($taken[$ledger->id]) || (! $account->isCredit() && $ledger->status->value === 'pending')) {
                continue;
            }
            $ledgers[] = ['t' => $ledger, 'direction' => $direction, 'cents' => FinancialEffects::cents((string) $ledger->amount),
                'names' => self::nameWords($ledger->description)];
        }

        $sorted = $bank->sortBy(fn (BankTx $t) => $t->date->toDateString().'|'.$t->id)->values();
        // People's names on the account statement (Pix). On a card the names are stores, spelled differently
        // from the ledger ("Mercadolivre*" × "Mercado Livre"): they do not keep records away.
        $statementNames = [];
        foreach ($account->isCredit() ? [] : $sorted as $b) {
            foreach (self::nameWords($b->description) as $word) {
                $statementNames[$word] = true;
            }
        }

        $used = [];
        $result = [];
        foreach ([true, false] as $named) {
            foreach ($sorted as $b) {
                if (! isset($result[$b->id]) && ($ledger = $this->bestSingle($b, $ledgers, $used, $named, $statementNames))) {
                    $used[$ledger->id] = true;
                    $result[$b->id] = $ledger->id;
                }
            }
            if ($account->isCredit()) {
                continue;
            }
            foreach ($sorted as $b) {
                if (! isset($result[$b->id]) && ($pair = $this->bestPair($b, $ledgers, $used, $named, $statementNames))) {
                    $used[$pair[0]->id] = $used[$pair[1]->id] = true;
                    $result[$b->id] = $pair[0]->id;
                }
            }
        }

        return $result;
    }

    /**
     * Whether a ledger record may go to this bank transaction in this pass: in the named pass it must name
     * the counterpart; otherwise it must not name another counterpart of the statement.
     *
     * @param  array{names: list<string>}  $ledger
     * @param  list<string>  $names
     * @param  array<string, bool>  $statementNames
     */
    private static function fitsCounterpart(array $ledger, array $names, bool $named, array $statementNames): bool
    {
        $own = $names !== [] && array_intersect($names, $ledger['names']) !== [];
        if ($named) {
            return $own;
        }

        return $own || array_intersect_key(array_flip($ledger['names']), $statementNames) === [];
    }

    /**
     * @param  list<array{t: Transaction, direction: string, cents: int, names: list<string>}>  $ledgers
     * @param  array<string, bool>  $used
     * @param  array<string, bool>  $statementNames
     */
    private function bestSingle(BankTx $b, array $ledgers, array $used, bool $named, array $statementNames): ?Transaction
    {
        $names = self::nameWords($b->description);
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($ledgers as $l) {
            $t = $l['t'];
            if (isset($used[$t->id]) || $l['direction'] !== $b->direction || $l['cents'] !== $b->amount_cents) {
                continue;
            }
            $days = (int) abs($b->date->diffInDays($t->transaction_date));
            $sameInstallment = $b->installment_number !== null
                && $t->installment_number === $b->installment_number
                && $t->total_installments === $b->total_installments;
            if ($days > ($sameInstallment ? self::INSTALLMENT_DAYS : self::MATCH_DAYS)) {
                continue;
            }
            // Installments are told apart by their number, not by names (store names differ on the card)
            if (! $sameInstallment && ! self::fitsCounterpart($l, $names, $named, $statementNames)) {
                continue;
            }
            if ($sameInstallment && $named) {
                continue;
            }
            $score = $days - ($sameInstallment ? 1000 : 0);
            if ($score < $bestScore) {
                [$best, $bestScore] = [$t, $score];
            }
        }

        return $best;
    }

    /**
     * Two confirmed records on the same days whose sum is the bank value (two lunches in one Pix). The
     * bank transaction links to the one naming the counterpart, if any.
     *
     * @param  list<array{t: Transaction, direction: string, cents: int, names: list<string>}>  $ledgers
     * @param  array<string, bool>  $used
     * @param  array<string, bool>  $statementNames
     * @return array{0: Transaction, 1: Transaction}|null
     */
    private function bestPair(BankTx $b, array $ledgers, array $used, bool $named, array $statementNames): ?array
    {
        $names = self::nameWords($b->description);
        $near = array_values(array_filter($ledgers, fn (array $l) => ! isset($used[$l['t']->id])
            && $l['direction'] === $b->direction && $l['t']->status->value === 'confirmed'
            && abs($b->date->diffInDays($l['t']->transaction_date)) <= self::MATCH_DAYS
            && self::fitsCounterpart($l, $names, false, $statementNames)));
        $own = fn (array $l) => $names !== [] && array_intersect($names, $l['names']) !== [];
        $count = count($near);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($near[$i]['cents'] + $near[$j]['cents'] !== $b->amount_cents) {
                    continue;
                }
                $first = $own($near[$i]) ? $near[$i] : ($own($near[$j]) ? $near[$j] : null);
                if ($named && $first === null) {
                    continue;
                }
                $first ??= $near[$i];
                $second = $first === $near[$i] ? $near[$j] : $near[$i];

                return [$first['t'], $second['t']];
            }
        }

        return null;
    }

    /** Ledger transactions that move the linked account or card. */
    private function ledgerSide(BankConnectionAccount $account): Builder
    {
        $query = Transaction::where('user_id', $account->user_id)
            ->where(fn ($q) => $q->whereNull('reconciliation_status')->orWhere('reconciliation_status', '!=', 'conflict'));
        if ($account->isCredit()) {
            return $query->where('card_id', $account->credit_card_id);
        }
        $ledgerId = $account->bank_account_id;

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('account_id', $ledgerId)->whereNull('card_id')->whereIn('type', ['income', 'expense']))
            ->orWhere(fn ($q) => $q->where('type', 'transfer')->where(fn ($q) => $q->where('account_id', $ledgerId)->orWhere('destination_account_id', $ledgerId))));
    }

    /** Whether a ledger transaction brings money into (in) or takes it out of (out) the linked side. */
    private function ledgerDirection(BankConnectionAccount $account, Transaction $t): ?string
    {
        $type = $t->type->value;
        if ($account->isCredit()) {
            return match ($type) {
                'expense' => 'out',
                'income', 'transfer' => 'in',
                default => null,
            };
        }
        if ($type === 'transfer') {
            return $t->account_id === $account->bank_account_id ? 'out' : 'in';
        }

        return $type === 'expense' ? 'out' : 'in';
    }

    // ── Review list and suggestions ────────────────────────────────────────────

    /**
     * Bank transactions waiting for a decision, oldest first, each with a suggestion.
     *
     * @return list<array<string, mixed>>
     */
    public function reviewItems(User $user): array
    {
        $rows = BankTx::where('user_id', $user->id)->where('review_status', BankTx::NEW)
            ->with(['account.bankConnection', 'account.bankAccount', 'account.creditCard'])
            ->orderBy('date')->orderBy('created_at')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $rules = BankReviewRule::where('user_id', $user->id)->get()->keyBy(fn (BankReviewRule $r) => $r->direction.'|'.$r->match_key);
        $history = $this->history($user);
        // What is already known about each counterpart (a category) keeps forecasts of another category away
        $known = $rows->mapWithKeys(function (BankTx $b) use ($rules, $history) {
            $key = $b->direction.'|'.self::matchKey($b->description);

            return [$b->id => $rules->get($key)?->category_id ?? $history[$key]['category_id'] ?? null];
        })->all();
        $forecasts = $this->forecastSuggestions($user, $rows, $known);
        $selfKey = self::matchKey((string) $user->name);

        return $rows->map(fn (BankTx $b) => [
            'transaction' => $b,
            'suggestion' => $this->suggest($user, $b, $rules, $history, $forecasts[$b->id] ?? null, $selfKey),
        ])->all();
    }

    /**
     * @param  Collection<string, BankReviewRule>  $rules
     * @param  array<string, array<string, mixed>>  $history
     */
    private function suggest(User $user, BankTx $b, Collection $rules, array $history, ?Transaction $forecast, string $selfKey): array
    {
        $account = $b->account;
        $key = self::matchKey($b->description);
        $base = [
            'action' => $b->direction === 'out' ? 'expense' : 'income',
            'description' => self::counterpart($b->description),
            'category_id' => null,
            'is_reimbursement' => false,
            'account_id' => null,
            'transaction_id' => null,
            'card_id' => null,
            // Shared expense: the user's part (suggested from the split used last time)
            'own_share_cents' => null,
            'source' => null,
        ];
        $share = fn (?float $ratio) => $ratio && $b->direction === 'out' ? max(1, (int) round($b->amount_cents * $ratio)) : null;

        if ($forecast !== null) {
            // A pending forecast is confirmed with the bank's value and date; a record already confirmed
            // (card subscription realized on its date, bill confirmed in "A confirmar") is only linked
            return ['action' => $forecast->status->value === 'pending' && ! $account->isCredit() ? 'confirm' : 'match', 'transaction_id' => $forecast->id,
                'description' => $forecast->description, 'category_id' => $forecast->category_id, 'source' => 'forecast'] + $base;
        }

        // The bill payment: on the account it pays the card; on the card it is the payment received
        if (preg_match('/^pagamento (de fatura|recebido)/i', Str::ascii($b->description))) {
            $card = $account->isCredit() ? $account->creditCard : $this->cardPaidFrom($account);
            if ($card !== null) {
                $payer = $account->isCredit() ? $this->accountPayingCard($account) : $account->bank_account_id;

                return ['action' => 'pay_card', 'card_id' => $card->id, 'account_id' => $payer,
                    'description' => "Fatura {$card->name}", 'source' => 'pattern'] + $base;
            }
        }

        $rule = $rules[$b->direction.'|'.$key] ?? null;
        if ($rule !== null) {
            return ['action' => $rule->action, 'description' => $rule->description ?: $base['description'],
                'category_id' => $rule->category_id, 'account_id' => $rule->account_id,
                'is_reimbursement' => $rule->is_reimbursement, 'own_share_cents' => $share($rule->own_share_ratio), 'source' => 'rule'] + $base;
        }

        if ($found = $history[$b->direction.'|'.$key] ?? null) {
            $ownShare = $found['shared'] ? $share($found['own_share_ratio'] ?? null) : null;
            unset($found['shared'], $found['own_share_ratio']);

            return array_filter($found, fn ($value) => $value !== null) + ['own_share_cents' => $ownShare, 'source' => 'history'] + $base;
        }

        // Money between the user's own accounts (Pix to/from oneself)
        if (! $account->isCredit() && $selfKey !== '' && str_word_count($selfKey) >= 2 && str_starts_with($key, $selfKey)) {
            return ['action' => 'transfer', 'description' => 'Transferência entre contas', 'source' => 'pattern'] + $base;
        }

        return $base;
    }

    /** Recent choices weigh: only the latest occurrences of a counterpart vote. */
    private const HISTORY_VOTES = 12;

    /**
     * What the user did before with each counterpart, learned from bank transactions already tied to the
     * ledger (matched or imported). Old statement imports nobody classified (no category) do not teach
     * anything; among the latest HISTORY_VOTES choices the most frequent wins, the most recent on a tie.
     *
     * @return array<string, array<string, mixed>> "direction|key" => suggestion fields (null = use the default)
     */
    private function history(User $user): array
    {
        $choices = [];
        $linked = BankTx::where('user_id', $user->id)->whereIn('review_status', BankTx::LINKED)
            ->whereNotNull('transaction_id')->with(['transaction', 'account'])->orderBy('date')->get();
        foreach ($linked as $b) {
            $t = $b->transaction;
            if ($t === null || $t->deleted_at !== null) {
                continue;
            }
            $legacy = $t->import_batch_id !== null;
            $classified = $t->category_id !== null || in_array($t->economic_kind, ['reimbursement', 'benefit'], true);
            $fields = match ($t->type->value) {
                'expense', 'income' => $legacy && ! $classified ? null : [
                    'action' => $t->type->value,
                    'description' => $legacy ? null : preg_replace('/\s*\(\d+\/\d+\)$/', '', $t->description),
                    'category_id' => $t->category_id,
                    'is_reimbursement' => $t->economic_kind === 'reimbursement',
                    'shared' => $t->economic_kind === 'shared_expense',
                    'account_id' => null,
                ],
                default => $t->card_id !== null ? null : [
                    'action' => 'transfer',
                    'description' => $legacy ? 'Transferência entre contas' : $t->description,
                    'category_id' => null,
                    'is_reimbursement' => false,
                    'shared' => false,
                    'account_id' => $b->direction === 'out' ? $t->destination_account_id : $t->account_id,
                ],
            };
            if ($fields !== null) {
                // The classification votes; the description comes from the latest occurrence that has one,
                // preferably one that names the same person as the bank ("Reembolso Peterson" for PETERSON)
                $description = $fields['description'];
                unset($fields['description']);
                $named = array_intersect(self::nameWords($b->description), self::nameWords($t->description)) !== [];
                $ratio = $fields['shared'] ? self::ownShareRatio($t) : null;
                $choices[$b->direction.'|'.self::matchKey($b->description)][] = [json_encode($fields), $description, $named, $ratio];
            }
        }

        $result = [];
        foreach ($choices as $key => $occurrences) {
            $votes = [];
            $descriptions = [];
            $namedDescriptions = [];
            $ratios = [];
            foreach (array_slice($occurrences, -self::HISTORY_VOTES) as $position => [$signature, $description, $named, $ratio]) {
                $votes[$signature] = [($votes[$signature][0] ?? 0) + 1, $position];
                if ($description !== null) {
                    $descriptions[$signature] = $description;
                    if ($named) {
                        $namedDescriptions[$signature] = $description;
                    }
                }
                if ($ratio !== null) {
                    $ratios[$signature] = $ratio;
                }
            }
            uasort($votes, fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);
            $winner = array_key_first($votes);
            $result[$key] = json_decode($winner, true) + [
                'description' => $namedDescriptions[$winner] ?? $descriptions[$winner] ?? null,
                'own_share_ratio' => $ratios[$winner] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Pending forecasts (bills, salary) on the same ledger account, close in date and value. A bill paid as
     * a boleto ("Pagamento efetuado") may vary more than a Pix; a counterpart already known for another
     * category is never offered the forecast. Each forecast goes to at most one bank transaction: the closest
     * pairs win.
     *
     * @param  Collection<int, BankTx>  $rows
     * @param  array<string, string|null>  $known  bank transaction id => category already known for its counterpart
     * @return array<string, Transaction>
     */
    private function forecastSuggestions(User $user, Collection $rows, array $known = []): array
    {
        $candidates = $rows->filter(fn (BankTx $b) => $b->account->isCredit()
            ? $b->account->credit_card_id && $b->direction === 'out'
            : (bool) $b->account->bank_account_id);
        if ($candidates->isEmpty()) {
            return [];
        }
        $window = [
            Carbon::parse($candidates->min('date'))->subDays(self::FORECAST_DAYS)->toDateString(),
            Carbon::parse($candidates->max('date'))->addDays(self::FORECAST_DAYS)->toDateString(),
        ];
        // Account forecasts are pending rows; on a card, a series occurrence (subscription) may already be
        // realized by the scheduler: any of its rows not tied to the bank yet is a candidate
        $linked = BankTx::where('user_id', $user->id)->whereIn('review_status', BankTx::LINKED)->whereNotNull('transaction_id')->pluck('transaction_id');
        $forecasts = Transaction::where('user_id', $user->id)->whereIn('type', ['income', 'expense'])
            ->whereBetween('transaction_date', $window)
            ->where(fn ($q) => $q
                // Forecasts on the account: pending ones, or a series occurrence the user already confirmed in
                // "A confirmar" (with the forecast value) that no bank transaction is tied to yet
                ->where(fn ($q) => $q->whereNull('card_id')
                    ->whereIn('account_id', $candidates->pluck('account.bank_account_id')->filter()->unique()->values())
                    ->where(fn ($q) => $q->where('status', 'pending')
                        ->orWhere(fn ($q) => $q->whereNotNull('recurrence_group_id')->whereNotIn('id', $linked))))
                ->orWhere(fn ($q) => $q->whereNotNull('recurrence_group_id')->where('type', 'expense')
                    ->whereIn('card_id', $candidates->pluck('account.credit_card_id')->filter()->unique()->values())
                    ->whereNotIn('id', $linked)))
            ->get();

        $pairs = [];
        foreach ($candidates as $b) {
            foreach ($forecasts as $f) {
                $type = $b->direction === 'out' ? 'expense' : 'income';
                $sameSide = $b->account->isCredit()
                    ? $f->card_id === $b->account->credit_card_id
                    : $f->card_id === null && $f->account_id === $b->account->bank_account_id;
                if (! $sameSide || $f->type->value !== $type) {
                    continue;
                }
                if (($known[$b->id] ?? null) !== null && $known[$b->id] !== $f->category_id) {
                    continue;
                }
                $expected = FinancialEffects::cents((string) $f->amount);
                $ratio = $expected > 0 ? abs($b->amount_cents - $expected) / $expected : 1;
                $days = (int) abs($b->date->diffInDays($f->transaction_date));
                $tolerance = preg_match('/^pagamento efetuado/i', Str::ascii($b->description)) ? self::BILL_TOLERANCE : self::FORECAST_TOLERANCE;
                if ($ratio <= $tolerance && $days <= self::FORECAST_DAYS) {
                    $pairs[] = [$ratio + $days / self::FORECAST_DAYS * 0.5, $b->id, $f];
                }
            }
        }
        usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);

        $result = [];
        $usedForecasts = [];
        foreach ($pairs as [, $bankId, $forecast]) {
            if (! isset($result[$bankId]) && ! isset($usedForecasts[$forecast->id])) {
                $result[$bankId] = $forecast;
                $usedForecasts[$forecast->id] = true;
            }
        }

        return $result;
    }

    // ── Applying the user's decisions ──────────────────────────────────────────

    /**
     * @param  list<array<string, mixed>>  $items  {id, action, category_id?, description?, account_id?, transaction_id?, card_id?, is_reimbursement?, remember?}
     * @return array{applied: int, skipped: list<array{id: string, message: string}>}
     */
    public function apply(User $user, array $items): array
    {
        $applied = 0;
        $skipped = [];
        foreach ($items as $item) {
            try {
                DB::transaction(fn () => $this->applyOne($user, $item));
                $applied++;
            } catch (ValidationException $e) {
                $skipped[] = ['id' => (string) $item['id'], 'message' => collect($e->errors())->flatten()->first() ?? 'Não foi possível lançar.'];
            }
        }
        // The other side of a transfer or a card payment is matched right away
        $this->reconcileUser($user->id);

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    private function applyOne(User $user, array $item): void
    {
        /** @var BankTx|null $b */
        $b = BankTx::where('user_id', $user->id)->whereKey($item['id'])->lockForUpdate()->first();
        if ($b === null || $b->review_status !== BankTx::NEW) {
            throw ValidationException::withMessages(['id' => 'Esta transação já foi revisada.']);
        }
        $account = $b->account;
        if (! $account->isLinked()) {
            throw ValidationException::withMessages(['id' => 'Ligue a conta do banco a uma conta do app antes de lançar.']);
        }
        $action = $item['action'];

        if ($action === 'ignore') {
            $b->forceFill(['review_status' => BankTx::IGNORED, 'reviewed_at' => now()])->save();

            return;
        }

        // Something the other side already created (a transfer, a card payment) is linked, not duplicated
        if (in_array($action, ['transfer', 'pay_card'], true) && ($ledgerId = $this->match($account, collect([$b]))[$b->id] ?? null)) {
            $this->linkTo($b, $ledgerId, BankTx::MATCHED);

            return;
        }

        $amount = $b->amount_cents / 100;
        $date = $b->date->toDateString();
        $description = Str::limit(trim((string) ($item['description'] ?? '')) ?: self::counterpart($b->description), 497);

        switch ($action) {
            case 'match':
            case 'confirm':
                $ledger = $this->ledgerSide($account)->whereKey($item['transaction_id'] ?? null)->first();
                if ($ledger === null || BankTx::where('transaction_id', $ledger->id)->where('bank_connection_account_id', $account->id)->whereIn('review_status', BankTx::LINKED)->exists()) {
                    throw ValidationException::withMessages(['transaction_id' => 'Escolha um lançamento desta conta que ainda não esteja ligado ao banco.']);
                }
                if ($action === 'confirm') {
                    if ($ledger->status->value !== 'pending') {
                        throw ValidationException::withMessages(['transaction_id' => 'Este lançamento já foi confirmado.']);
                    }
                    $this->transactions->confirm($ledger, $amount, $date);
                }
                $this->linkTo($b, $ledger->id, BankTx::MATCHED);

                return;

            case 'expense':
            case 'income':
                $type = TransactionType::from($action);
                $categoryId = $this->category($user, $item['category_id'] ?? null, $action);
                if ($b->total_installments) {
                    $description = preg_replace('/\s*\(\d+\/\d+\)$/', '', $description)." ({$b->installment_number}/{$b->total_installments})";
                }
                $dto = new TransactionDTO(
                    type: $type,
                    amount: $amount,
                    description: $description,
                    transactionDate: $date,
                    accountId: $account->isCredit() ? null : $account->bank_account_id,
                    cardId: $account->isCredit() ? $account->credit_card_id : null,
                    categoryId: $categoryId,
                    isReimbursement: $type === TransactionType::Income ? (bool) ($item['is_reimbursement'] ?? false) : null,
                );
                $ledger = $this->transactions->create($user, $dto);
                // Split with others: only the user's part counts as spending; their parts come back as reimbursements
                $ownShare = (int) ($item['own_share_cents'] ?? 0);
                if ($type === TransactionType::Expense && $ownShare > 0 && $ownShare < $b->amount_cents) {
                    $ledger->forceFill([
                        'economic_kind' => 'shared_expense', 'classification_status' => 'identified',
                        'economic_expense_cents' => $ownShare, 'economic_income_cents' => 0,
                    ])->save();
                }
                break;

            case 'transfer':
                if ($account->isCredit()) {
                    throw ValidationException::withMessages(['action' => 'No cartão, use compra, estorno ou pagamento da fatura.']);
                }
                $other = BankAccount::forUser($user->id)->whereKey($item['account_id'] ?? null)->where('id', '!=', $account->bank_account_id)->first();
                if ($other === null) {
                    throw ValidationException::withMessages(['account_id' => 'Escolha a outra conta da transferência.']);
                }
                $out = $b->direction === 'out';
                $ledger = $this->transactions->create($user, new TransactionDTO(
                    type: TransactionType::Transfer,
                    amount: $amount,
                    description: $description,
                    transactionDate: $date,
                    accountId: $out ? $account->bank_account_id : $other->id,
                    destinationAccountId: $out ? $other->id : $account->bank_account_id,
                ));
                break;

            case 'pay_card':
                [$card, $payerId] = $account->isCredit()
                    ? [$account->creditCard, $item['account_id'] ?? $this->accountPayingCard($account)]
                    : [CreditCard::forUser($user->id)->whereKey($item['card_id'] ?? $this->cardPaidFrom($account)?->id)->first(), $account->bank_account_id];
                if ($card === null || ! BankAccount::forUser($user->id)->whereKey($payerId)->exists()) {
                    throw ValidationException::withMessages(['card_id' => 'Escolha o cartão e a conta que pagou a fatura.']);
                }
                if (($b->direction === 'out') === $account->isCredit()) {
                    throw ValidationException::withMessages(['action' => 'Isto não é um pagamento de fatura.']);
                }
                $ledger = $this->payCard->execute($user, $card, $payerId, $amount, $this->statementPaid($card, $b->date), $date);
                break;

            default:
                throw ValidationException::withMessages(['action' => 'Ação inválida.']);
        }

        $this->linkTo($b, $ledger->id, BankTx::IMPORTED);
        if (($item['remember'] ?? true) && in_array($action, ['expense', 'income', 'transfer'], true)) {
            $this->remember($user, $b, $action, $ledger);
        }
    }

    /** Takes a decision back: an ignored transaction returns to review; an imported one is deleted from the ledger. */
    public function undo(BankTx $b): void
    {
        DB::transaction(function () use ($b) {
            $b = BankTx::whereKey($b->id)->lockForUpdate()->firstOrFail();
            if ($b->review_status === BankTx::IGNORED) {
                $b->forceFill(['review_status' => BankTx::NEW, 'reviewed_at' => null])->save();

                return;
            }
            if ($b->review_status !== BankTx::IMPORTED) {
                throw ValidationException::withMessages(['id' => 'Só dá para desfazer o que foi lançado ou ignorado aqui.']);
            }
            $ledger = Transaction::find($b->transaction_id);
            $b->forceFill(['review_status' => BankTx::NEW, 'transaction_id' => null, 'reviewed_at' => null])->save();
            if ($ledger !== null) {
                $this->transactions->delete($ledger);
            }
        });
        $this->reconcileUser($b->user_id);
    }

    private function linkTo(BankTx $b, string $ledgerId, string $status): void
    {
        $b->forceFill(['review_status' => $status, 'transaction_id' => $ledgerId, 'reviewed_at' => now()])->save();
    }

    private function remember(User $user, BankTx $b, string $action, Transaction $ledger): void
    {
        $rule = BankReviewRule::firstOrNew(['user_id' => $user->id, 'match_key' => self::matchKey($b->description), 'direction' => $b->direction]);
        $rule->fill([
            'action' => $action,
            'category_id' => $ledger->category_id,
            'account_id' => $action === 'transfer' ? ($b->direction === 'out' ? $ledger->destination_account_id : $ledger->account_id) : null,
            'description' => Str::limit(preg_replace('/\s*\(\d+\/\d+\)$/', '', $ledger->description), 252),
            'is_reimbursement' => $ledger->economic_kind === 'reimbursement',
            'own_share_ratio' => $ledger->economic_kind === 'shared_expense' ? self::ownShareRatio($ledger) : null,
            'uses' => $rule->uses + 1,
        ])->save();
    }

    /** The user's part of a shared expense, as a share of its amount (83.00 with 20.75 of it → 0.25). */
    private static function ownShareRatio(Transaction $t): ?float
    {
        $amount = FinancialEffects::cents((string) $t->amount);

        return $t->economic_expense_cents !== null && $amount > 0 ? round($t->economic_expense_cents / $amount, 4) : null;
    }

    private function category(User $user, ?string $categoryId, string $type): ?string
    {
        if ($categoryId === null) {
            return null;
        }
        if (! TransactionCategory::where('user_id', $user->id)->whereKey($categoryId)->where('type', $type)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Escolha uma categoria de '.($type === 'expense' ? 'despesa' : 'receita').'.']);
        }

        return $categoryId;
    }

    /** The card a bank account pays: the one linked in the same connection, else the user's only card. */
    private function cardPaidFrom(BankConnectionAccount $account): ?CreditCard
    {
        $linked = BankConnectionAccount::where('bank_connection_id', $account->bank_connection_id)->whereNotNull('credit_card_id')->value('credit_card_id');
        if ($linked) {
            return CreditCard::find($linked);
        }
        $cards = CreditCard::forUser($account->user_id)->where('is_active', true)->get();

        return $cards->count() === 1 ? $cards->first() : null;
    }

    /** The account that pays a card: the bank account linked in the same connection, else the card's own. */
    private function accountPayingCard(BankConnectionAccount $card): ?string
    {
        return BankConnectionAccount::where('bank_connection_id', $card->bank_connection_id)->whereNotNull('bank_account_id')->value('bank_account_id')
            ?? $card->creditCard?->bank_account_id;
    }

    /** The statement a payment on this date settles: the cycle whose due date is closest. */
    private function statementPaid(CreditCard $card, Carbon $date): string
    {
        $base = $date->format('Y-m');
        $cycles = [CardCycle::shift($base, -1), $base, CardCycle::shift($base, 1)];
        usort($cycles, fn ($a, $b) => abs($date->diffInDays(CardCycle::dueDate($card, $a))) <=> abs($date->diffInDays(CardCycle::dueDate($card, $b))));

        return $cycles[0];
    }

    // ── Text helpers ───────────────────────────────────────────────────────────

    /**
     * Stable key for a counterpart: "Transferência enviada pelo Pix|SAFE2PAY INTERMEDIACAO DE NEG" and
     * "Transferência enviada|SAFE2PAY INTERMEDIACAO DE NEGOCIOS LTD" both become "SAFE2PAY INTERMEDIAC".
     * Installment numbers, digits and punctuation are dropped.
     */
    public static function matchKey(string $description): string
    {
        $text = str_contains($description, '|') ? Str::after($description, '|') : $description;
        $text = Str::upper(Str::ascii($text));
        $text = preg_replace('/\b\d+\/\d+\b/', ' ', $text);
        $text = preg_replace('/[^A-Z ]+/', ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return trim(Str::limit($text, 20, ''));
    }

    /**
     * Words that can name a person or a business ("PETERSON", "SAFE2PAY"): 4+ letters, accents and case dropped,
     * common statement words left out.
     *
     * @return list<string>
     */
    public static function nameWords(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii(self::counterpart($text))), -1, PREG_SPLIT_NO_EMPTY);
        $common = ['transferencia', 'enviada', 'recebida', 'recebido', 'pelo', 'reembolso', 'pagamento', 'efetuado', 'almoco', 'compra', 'ltda'];

        return array_values(array_diff(array_filter($words, fn ($w) => strlen($w) >= 4), $common));
    }

    /** "Transferência enviada pelo Pix|SAFE2PAY INTERMEDIACAO" → "SAFE2PAY INTERMEDIACAO"; "Loja 2/5" → "Loja" */
    public static function counterpart(string $description): string
    {
        $text = trim(str_contains($description, '|') ? Str::after($description, '|') : $description);
        $text = trim(preg_replace('/\s*\b\d+\/\d+$/', '', $text));

        return $text !== '' ? $text : trim($description);
    }
}
