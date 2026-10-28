<?php

namespace NYCorp\Finance\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Integrity\AcknowledgedTransactions;
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
            ->where('created_at', '<', now()->subMinutes($minutes));

        if ($this->option('dry-run')) {
            $this->info((clone $query)->count() . " pending transaction(s) older than $minutes minute(s).");
            return self::SUCCESS;
        }

        $canceled = $acknowledged = $failed = 0;
        // chunkById pages on id > last id: any other order would overlap pages and skip rows
        $query->chunkById(200, function ($transactions) use (&$canceled, &$acknowledged, &$failed, $minutes) {
            foreach ($transactions as $transaction) {
                // Canceling would checksum a row whose start signature fails: leave it PENDING, the proof intact
                if (AcknowledgedTransactions::accepts($transaction)) {
                    $acknowledged++;
                    $this->warn("Skipped acknowledged transaction {$transaction->id}: " . AcknowledgedTransactions::reason($transaction));
                    continue;
                }

                try {
                    FinanceTransactionController::close($transaction, FinanceTransaction::STATE_CANCELED, ['reason' => 'expired', 'after_minutes' => $minutes]);
                } catch (\Throwable $e) {
                    // One bad row must not block the others; the message of other exceptions may hold data
                    $failed++;
                    $reason = $e instanceof FinanceException ? $e->getMessage() : $e::class;
                    Log::error("finance:expire-pending could not cancel transaction {$transaction->id}", ['reason' => $reason]);
                    $this->error("Skipped transaction {$transaction->id}: $reason");
                    continue;
                }

                if ($transaction->fresh()?->state === FinanceTransaction::STATE_CANCELED) {
                    $canceled++;
                }
            }
        }, FinanceTransaction::ID);

        $skipped = $acknowledged + $failed;
        $this->info("$canceled pending transaction(s) canceled, $skipped skipped ($acknowledged acknowledged, $failed failed).");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
