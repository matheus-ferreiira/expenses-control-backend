<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetUserDataAction
{
    public function execute(User $user): void
    {
        $userId = $user->id;

        DB::transaction(function () use ($userId, $user) {
            // Finance: transactions cascade-deletes transaction_transaction_tag via FK
            DB::table('transactions')->where('user_id', $userId)->delete();
            DB::table('credit_cards')->where('user_id', $userId)->delete();
            DB::table('bank_accounts')->where('user_id', $userId)->delete();
            DB::table('transaction_categories')->where('user_id', $userId)->delete();
            DB::table('transaction_tags')->where('user_id', $userId)->delete();

            // Tasks: tasks cascade-deletes task_label_task + subtasks via FK
            DB::table('tasks')->where('user_id', $userId)->delete();
            DB::table('task_labels')->where('user_id', $userId)->delete();

            // Habits: habits cascade-deletes habit_logs via FK
            DB::table('habits')->where('user_id', $userId)->delete();

            // Other domains
            DB::table('goals')->where('user_id', $userId)->delete();
            DB::table('calendar_events')->where('user_id', $userId)->delete();

            // Notes: notes cascade-deletes note_note_tag via FK
            DB::table('notes')->where('user_id', $userId)->delete();
            DB::table('note_tags')->where('user_id', $userId)->delete();

            DB::table('purchase_items')->where('user_id', $userId)->delete();

            // Reset user counters
            $user->update([
                'current_streak' => 0,
                'last_transaction_date' => null,
            ]);
        });
    }
}
