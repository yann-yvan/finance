<?php

namespace NYCorp\Finance\Console;

use Illuminate\Console\Command;
use NYCorp\Finance\Integrity\AuditTrail;
use NYCorp\Finance\Integrity\FinanceSchema;
use NYCorp\Finance\Integrity\HashChain;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceTransaction;

class SealHistoryCommand extends Command
{
    protected $signature = 'finance:seal-history {--dry-run : Only count the transactions to seal}';

    protected $description = 'Append the finalized transactions created before the hash chain existed to the chain (run once after the upgrade)';

    public function handle(): int
    {
        if (!FinanceSchema::hasChain()) {
            $this->error('The finance_chain_entries table is missing: publish and run the finance migrations first.');
            return self::FAILURE;
        }

        $query = FinanceTransaction::where(FinanceTransaction::IS_LOCKED, true)
            ->whereNotExists(static function ($query) {
                $query->selectRaw(1)->from('finance_chain_entries')
                    ->whereColumn('finance_chain_entries.finance_transaction_id', 'finance_transactions.id');
            })
            ->orderBy(FinanceTransaction::VERIFY_AT)
            ->orderBy('created_at')
            ->orderBy(FinanceTransaction::ID);

        $total = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("$total transaction(s) to seal.");
            return self::SUCCESS;
        }

        $sealed = 0;
        $invalid = 0;
        // Each pass re-reads the first unsealed rows, so sealing does not shift the window
        while (($transactions = (clone $query)->limit(500)->get())->isNotEmpty()) {
            foreach ($transactions as $transaction) {
                if (!$transaction->verifyChecksum()) {
                    $invalid++;
                    $this->warn("Transaction {$transaction->id} has an invalid checksum: sealed as is, see finance:verify-integrity");
                }
                HashChain::append($transaction);
                $sealed++;
            }
        }

        AuditTrail::record(FinanceAuditLog::EVENT_HISTORY_SEALED, null, null, ['sealed' => $sealed, 'invalid_checksum' => $invalid]);
        $this->info("$sealed transaction(s) sealed in the hash chain.");

        return self::SUCCESS;
    }
}
