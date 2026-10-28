<?php

namespace NYCorp\Finance\Integrity;

use Illuminate\Support\Facades\DB;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceChainEntry;
use NYCorp\Finance\Models\FinanceLedger;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;
use NYCorp\Finance\Traits\FinanceAccountTrait;

/**
 * Read-only verification of every integrity guarantee of the library.
 *
 * Errors are proofs of tampering or inconsistency; warnings are weaknesses (legacy v1 rows, unsealed history, ...).
 */
class IntegrityVerifier
{
    private const EPSILON = 0.00001;

    private array $errors = [];
    private array $warnings = [];
    private array $stats = [];

    public function run(bool $fixBalances = false): static
    {
        $this->errors = $this->warnings = $this->stats = [];

        if (!Signer::isEnabled()) {
            $this->warning('signature', null, 'No FINANCE_SIGNATURE_KEY configured: new rows use unkeyed hashes that anyone with database access can recompute');
        }

        $this->verifyTransactions();
        $this->verifyWallets();
        $this->verifyLedgers();
        $this->verifyChain();
        $this->verifyBalances($fixBalances);
        $this->verifyAuditLog();

        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function stats(): array
    {
        return $this->stats;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    private function verifyTransactions(): void
    {
        $versions = [];
        $count = 0;
        $acknowledged = [];
        foreach (ConfigReader::getAcknowledgedTransactions() as $id => $reason) {
            $acknowledged[(string)$id] = (string)$reason;
        }
        $seen = [];
        $accepted = 0;

        FinanceTransaction::query()->orderBy(FinanceTransaction::ID)->chunk(500, function ($transactions) use (&$versions, &$count, $acknowledged, &$seen, &$accepted) {
            foreach ($transactions as $transaction) {
                $count++;
                $version = (int)($transaction->signature_version ?? 1);
                $versions["v$version"] = ($versions["v$version"] ?? 0) + 1;

                // Signature failures, which an acknowledgement can turn into warnings
                $failures = [];
                if (!$transaction->hasValidStartSignature()) {
                    $failures[] = 'Start signature does not match the transaction (amount, currency, description or provider altered)';
                }

                if ($transaction->is_locked) {
                    if (!$transaction->requiresFinalization()) {
                        $this->error('transaction', $transaction->id, "Locked transaction in state {$transaction->state}");
                    } elseif (!$transaction->verifyChecksum()) {
                        $failures[] = 'Checksum does not match the finalized transaction';
                    }
                } elseif ($transaction->requiresFinalization()) {
                    $this->error('transaction', $transaction->id, "Transaction in state {$transaction->state} is not locked");
                }

                $id = (string)$transaction->id;
                if (!array_key_exists($id, $acknowledged)) {
                    foreach ($failures as $failure) {
                        $this->error('transaction', $id, $failure);
                    }
                    continue;
                }

                $seen[$id] = true;
                if (empty($failures)) {
                    $this->warning('transaction', $id, 'Acknowledged transaction now verifies: remove it from finance.integrity.acknowledged');
                } elseif (ConfigReader::getAcknowledgedMode() === 'rounding' && !$this->explainedByRounding($transaction)) {
                    foreach ($failures as $failure) {
                        $this->error('transaction', $id, "$failure (acknowledged, but the signature does not verify with the start_log amount either)");
                    }
                } else {
                    $accepted++;
                    foreach ($failures as $failure) {
                        $this->warning('transaction', $id, "$failure: acknowledged ({$acknowledged[$id]})");
                    }
                }
            }
        });

        foreach (array_diff_key($acknowledged, $seen) as $id => $reason) {
            $this->warning('transaction', $id, 'Acknowledged transaction does not exist: remove it from finance.integrity.acknowledged');
        }

        $this->stats['transactions'] = $count;
        $this->stats['transactions_by_signature'] = $versions;
        $this->stats['acknowledged_transactions'] = $accepted;
        if (($versions['v1'] ?? 0) > 0) {
            $this->warning('transaction', null, "{$versions['v1']} transaction(s) signed with v1, which hashes column names only and proves nothing");
        }
    }

    /**
     * Before 2.0 the amount was signed with up to 6 decimals, then rounded to 5 by the column. The row is genuine when
     * its signatures verify with the amount of the start_log (with the sign of the movement) and that amount rounds
     * to the stored one.
     */
    private function explainedByRounding(FinanceTransaction $transaction): bool
    {
        $logged = data_get($transaction->start_log, 'parameters.amount');
        if (!is_numeric($logged)) {
            return false;
        }

        $stored = (float)$transaction->amount;
        $signed = abs((float)$logged) * ($stored < 0 ? -1 : 1);
        if (abs(round($signed, 5) - $stored) > self::EPSILON) {
            return false;
        }

        $original = (clone $transaction)->setAttribute(FinanceTransaction::AMOUNT, $signed);
        return $original->hasValidStartSignature() && (!$original->is_locked || $original->verifyChecksum());
    }

    private function verifyWallets(): void
    {
        $count = 0;
        FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)->orderBy(FinanceWallet::ID)->chunk(500, function ($wallets) use (&$count) {
            foreach ($wallets as $wallet) {
                $count++;
                if (!$wallet->verifyChecksum()) {
                    $this->error('wallet', $wallet->id, 'Wallet checksum does not match (owner, transaction or source wallet altered)');
                }
            }
        });
        $this->stats['wallets'] = $count;
    }

    private function verifyLedgers(): void
    {
        $count = 0;
        FinanceLedger::withTrashed()->orderBy(FinanceLedger::ID)->chunk(500, function ($ledgers) use (&$count) {
            foreach ($ledgers as $ledger) {
                $count++;
                if (!$ledger->verifyChecksum()) {
                    $this->error('ledger', $ledger->id, 'Ledger checksum does not match (name, currency or owner altered)');
                }
            }
        });
        $this->stats['ledgers'] = $count;
    }

    private function verifyChain(): void
    {
        if (!FinanceSchema::hasChain()) {
            $this->warning('chain', null, 'Hash chain table missing: publish and run the finance migrations');
            return;
        }

        $count = 0;
        $owner = null;
        $previous = null;
        FinanceChainEntry::query()
            ->orderBy(FinanceChainEntry::OWNER_TYPE)
            ->orderBy(FinanceChainEntry::OWNER_ID)
            ->orderBy(FinanceChainEntry::OWNER_SEQ)
            ->with('transaction')
            ->chunk(500, function ($entries) use (&$count, &$owner, &$previous) {
                foreach ($entries as $entry) {
                    $count++;
                    $key = $entry->owner_type . '#' . $entry->owner_id;
                    if ($key !== $owner) {
                        $owner = $key;
                        $previous = null;
                    }

                    $expectedSeq = ($previous?->owner_seq ?? 0) + 1;
                    if ($entry->owner_seq !== $expectedSeq) {
                        $this->error('chain', $entry->id, "Owner $key: expected position $expectedSeq, found {$entry->owner_seq} (entry deleted or inserted)");
                    }
                    if ($entry->previous_hash !== $previous?->hash) {
                        $this->error('chain', $entry->id, "Owner $key: link to the previous entry is broken");
                    }
                    if (!$entry->verifyHash()) {
                        $this->error('chain', $entry->id, 'Chain entry hash does not match its content');
                    }

                    $transaction = $entry->transaction;
                    if ($transaction === null) {
                        $this->error('chain', $entry->id, "Sealed transaction {$entry->finance_transaction_id} no longer exists");
                    } elseif ($transaction->state !== $entry->state
                        || abs((float)$transaction->amount - (float)$entry->amount) > self::EPSILON
                        || $transaction->currency !== $entry->currency
                        || $transaction->checksum !== $entry->transaction_checksum) {
                        $this->error('chain', $entry->id, "Transaction {$transaction->id} differs from its sealed copy");
                    }

                    $previous = $entry;
                }
            });
        $this->stats['chain_entries'] = $count;

        // Autoincrement gaps also come from rolled back inserts: a hint, not a proof
        $ids = FinanceChainEntry::query()->selectRaw('COUNT(*) as total, MIN(id) as first, MAX(id) as last')->first();
        if ($ids && $ids->total > 0 && ((int)$ids->last - (int)$ids->first + 1) !== (int)$ids->total) {
            $this->warning('chain', null, 'Gaps in the global chain sequence (rolled back writes or deleted entries)');
        }

        $firstSealedAt = FinanceChainEntry::min(FinanceChainEntry::CREATED_AT);
        $unsealed = FinanceTransaction::where(FinanceTransaction::IS_LOCKED, true)
            ->whereNotExists(static function ($query) {
                $query->selectRaw(1)->from('finance_chain_entries')
                    ->whereColumn('finance_chain_entries.finance_transaction_id', 'finance_transactions.id');
            });

        $missing = $firstSealedAt === null ? collect() : (clone $unsealed)->where(FinanceTransaction::VERIFY_AT, '>=', $firstSealedAt)->pluck(FinanceTransaction::ID);
        foreach ($missing as $id) {
            $this->error('chain', $id, 'Finalized transaction missing from the hash chain');
        }

        $legacy = $unsealed->count() - $missing->count();
        $this->stats['unsealed_legacy_transactions'] = $legacy;
        if ($legacy > 0) {
            $this->warning('chain', null, "$legacy finalized transaction(s) older than the chain are not sealed (run finance:seal-history)");
        }
    }

    private function verifyBalances(bool $fix): void
    {
        $count = 0;
        FinanceAccount::query()->orderBy(FinanceAccount::ID)->chunk(500, function ($accounts) use (&$count, $fix) {
            foreach ($accounts as $account) {
                $count++;
                $expected = (float)DB::table('finance_transactions')
                    ->join('finance_wallets', 'finance_wallets.finance_transaction_id', '=', 'finance_transactions.id')
                    ->where('finance_wallets.owner_type', $account->owner_type)
                    ->where('finance_wallets.owner_id', $account->owner_id)
                    ->where('finance_transactions.currency', $account->currency)
                    ->where('finance_transactions.state', FinanceTransaction::STATE_COMPLETED)
                    ->where('finance_transactions.is_locked', true)
                    ->whereNotNull('finance_transactions.external_id')
                    ->whereNotNull('finance_transactions.checksum')
                    ->whereNotNull('finance_transactions.verify_at')
                    ->sum('finance_transactions.amount');

                if (!$account->is_account_active) {
                    $this->warning('account', $account->id, 'Account deactivated after an invalid transaction checksum');
                }

                if (abs($expected - (float)$account->credibility) <= self::EPSILON) {
                    continue;
                }

                $message = "Balance {$account->credibility} {$account->currency} differs from the transactions total $expected";
                $owner = $fix && class_exists($account->owner_type) ? $account->owner_type::find($account->owner_id) : null;
                if ($owner !== null && in_array(FinanceAccountTrait::class, class_uses_recursive($owner), true)) {
                    $owner->balanceChecksum(true, $account->currency);
                    $this->warning('account', $account->id, "$message: recalculated");
                } else {
                    $this->error('account', $account->id, $message);
                }
            }
        });
        $this->stats['accounts'] = $count;
    }

    private function verifyAuditLog(): void
    {
        if (!FinanceSchema::hasAuditLog()) {
            $this->warning('audit', null, 'Audit log table missing: publish and run the finance migrations');
            return;
        }

        $count = 0;
        FinanceAuditLog::query()->orderBy(FinanceAuditLog::ID)->chunk(500, function ($logs) use (&$count) {
            foreach ($logs as $log) {
                $count++;
                if (!$log->verifyHash()) {
                    $this->error('audit', $log->id, 'Audit row hash does not match its content');
                }
            }
        });
        $this->stats['audit_logs'] = $count;

        $ids = FinanceAuditLog::query()->selectRaw('COUNT(*) as total, MIN(id) as first, MAX(id) as last')->first();
        if ($ids && $ids->total > 0 && ((int)$ids->last - (int)$ids->first + 1) !== (int)$ids->total) {
            $this->warning('audit', null, 'Gaps in the audit log sequence (rolled back writes or deleted rows)');
        }
    }

    private function error(string $type, mixed $id, string $message): void
    {
        $this->errors[] = ['type' => $type, 'id' => $id === null ? null : (string)$id, 'message' => $message];
    }

    private function warning(string $type, mixed $id, string $message): void
    {
        $this->warnings[] = ['type' => $type, 'id' => $id === null ? null : (string)$id, 'message' => $message];
    }
}
