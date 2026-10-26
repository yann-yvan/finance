<?php

namespace NYCorp\Finance\Console;

use Illuminate\Console\Command;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Models\FinanceTransaction;

class ExpirePendingTransactionsCommand extends Command
{
    protected $signature = 'finance:expire-pending
                            {--minutes=10080 : Cancel the transactions pending for longer than this (default 7 days)}
                            {--dry-run : Only list them}';

    protected $description = 'Cancel the transactions left PENDING by a provider that never answered';

    public function handle(): int
    {
        $minutes = max(1, (int)$this->option('minutes'));
        $query = FinanceTransaction::where(FinanceTransaction::STATE, FinanceTransaction::STATE_PENDING)
            ->where(FinanceTransaction::IS_LOCKED, false)
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->orderBy('created_at');

        if ($this->option('dry-run')) {
            $this->info((clone $query)->count() . " pending transaction(s) older than $minutes minute(s).");
            return self::SUCCESS;
        }

        $canceled = 0;
        $query->chunkById(200, function ($transactions) use (&$canceled, $minutes) {
            foreach ($transactions as $transaction) {
                FinanceTransactionController::close($transaction, FinanceTransaction::STATE_CANCELED, ['reason' => 'expired', 'after_minutes' => $minutes]);
                if ($transaction->fresh()?->state === FinanceTransaction::STATE_CANCELED) {
                    $canceled++;
                }
            }
        }, FinanceTransaction::ID);

        $this->info("$canceled pending transaction(s) canceled.");
        return self::SUCCESS;
    }
}
