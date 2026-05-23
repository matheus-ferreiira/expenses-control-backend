<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\DTOs\BankAccountDTO;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class BankAccountService
{
    public function list(User $user): Collection
    {
        return BankAccount::forUser($user->id)->active()->with('creditCards')->get();
    }

    public function create(User $user, BankAccountDTO $dto): BankAccount
    {
        return BankAccount::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'type' => $dto->type,
            'bank_name' => $dto->bankName,
            'balance' => $dto->balance,
            'currency' => $dto->currency,
            'color' => $dto->color,
            'is_active' => $dto->isActive,
        ]);
    }

    public function update(BankAccount $account, BankAccountDTO $dto): BankAccount
    {
        $account->update([
            'name' => $dto->name,
            'type' => $dto->type,
            'bank_name' => $dto->bankName,
            'balance' => $dto->balance,
            'color' => $dto->color,
            'is_active' => $dto->isActive,
        ]);

        return $account;
    }

    public function delete(BankAccount $account): void
    {
        $account->delete();
    }

    public function getConsolidatedBalance(User $user): array
    {
        $accounts = BankAccount::forUser($user->id)->active()->get();

        $totalBalance = $accounts->sum('balance');
        $byCurrency = $accounts->groupBy('currency')->map(fn ($group) => $group->sum('balance'));

        return [
            'total' => $totalBalance,
            'by_currency' => $byCurrency,
            'accounts' => $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => $a->balance]),
        ];
    }

    public function createCreditCard(BankAccount $account, array $data): CreditCard
    {
        return $account->creditCards()->create([
            'user_id' => $account->user_id,
            ...$data,
        ]);
    }

    public function updateCreditCard(CreditCard $card, array $data): CreditCard
    {
        $card->update($data);

        return $card;
    }

    public function deleteCreditCard(CreditCard $card): void
    {
        $card->delete();
    }
}
