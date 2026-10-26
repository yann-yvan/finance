<?php

namespace NYCorp\Finance\Integrity;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use NYCorp\Finance\Models\FinanceChainEntry;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;

/**
 * Per-owner hash chain of finalized transactions: each entry signs the hash of the previous one,
 * so a deleted, inserted or modified entry breaks the chain.
 */
class HashChain
{
    private const MAX_ATTEMPTS = 3;

    public static function append(FinanceTransaction $transaction): ?FinanceChainEntry
    {
        if (!FinanceSchema::hasChain()) {
            return null;
        }

        $existing = FinanceChainEntry::where(FinanceChainEntry::FINANCE_TRANSACTION_ID, $transaction->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $wallet = FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)
            ->where(FinanceWallet::FINANCE_TRANSACTION_ID, $transaction->getKey())
            ->first();
        $ownerType = $wallet?->{FinanceWallet::OWNER_TYPE} ?? FinanceChainEntry::UNOWNED;
        $ownerId = (string)($wallet?->{FinanceWallet::OWNER_ID} ?? FinanceChainEntry::UNOWNED);

        for ($attempt = 1; ; $attempt++) {
            try {
                // Nested call = savepoint: a lost race on owner_seq only rolls back this insert
                return DB::transaction(static function () use ($transaction, $ownerType, $ownerId) {
                    $last = FinanceChainEntry::where(FinanceChainEntry::OWNER_TYPE, $ownerType)
                        ->where(FinanceChainEntry::OWNER_ID, $ownerId)
                        ->orderByDesc(FinanceChainEntry::OWNER_SEQ)
                        ->lockForUpdate()
                        ->first();

                    $entry = new FinanceChainEntry([
                        FinanceChainEntry::OWNER_TYPE => $ownerType,
                        FinanceChainEntry::OWNER_ID => $ownerId,
                        FinanceChainEntry::OWNER_SEQ => ($last?->{FinanceChainEntry::OWNER_SEQ} ?? 0) + 1,
                        FinanceChainEntry::FINANCE_TRANSACTION_ID => $transaction->getKey(),
                        FinanceChainEntry::AMOUNT => $transaction->{FinanceTransaction::AMOUNT},
                        FinanceChainEntry::CURRENCY => $transaction->{FinanceTransaction::CURRENCY},
                        FinanceChainEntry::STATE => $transaction->{FinanceTransaction::STATE},
                        FinanceChainEntry::TRANSACTION_CHECKSUM => $transaction->{FinanceTransaction::CHECKSUM},
                        FinanceChainEntry::PREVIOUS_HASH => $last?->{FinanceChainEntry::HASH},
                        FinanceChainEntry::CREATED_AT => now(),
                    ]);
                    $entry->{FinanceChainEntry::HASH} = Signer::sign($entry->payload());
                    $entry->save();

                    return $entry;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }
}
