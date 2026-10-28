<?php

namespace NYCorp\Finance\Integrity;

use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Models\FinanceTransaction;

/**
 * Legacy transactions whose signature failure was investigated and accepted (finance.integrity.acknowledged).
 *
 * They are never re-signed: their original signature stays the proof.
 */
class AcknowledgedTransactions
{
    private const EPSILON = 0.00001;

    /**
     * @return array<string, string> transaction id => reason
     */
    public static function all(): array
    {
        $acknowledged = [];
        foreach (ConfigReader::getAcknowledgedTransactions() as $id => $reason) {
            $acknowledged[(string)$id] = (string)$reason;
        }
        return $acknowledged;
    }

    public static function reason(FinanceTransaction|string $transaction): ?string
    {
        $id = $transaction instanceof FinanceTransaction ? (string)$transaction->id : $transaction;
        return self::all()[$id] ?? null;
    }

    /**
     * Listed and, in "rounding" mode, explained by the pre-2.0 rounding of the amount
     */
    public static function accepts(FinanceTransaction $transaction): bool
    {
        if (self::reason($transaction) === null) {
            return false;
        }
        return ConfigReader::getAcknowledgedMode() !== 'rounding' || self::explainedByRounding($transaction);
    }

    /**
     * Before 2.0 the amount was signed with up to 6 decimals, then rounded to 5 by the column. The row is genuine when
     * its signatures verify with the amount of the start_log (with the sign of the movement) and that amount rounds
     * to the stored one.
     */
    public static function explainedByRounding(FinanceTransaction $transaction): bool
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
}
