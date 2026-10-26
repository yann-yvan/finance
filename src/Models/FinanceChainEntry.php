<?php

namespace NYCorp\Finance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NYCorp\Finance\Integrity\Signer;

/**
 * Append-only hash chain entry sealing a finalized transaction.
 *
 * @property int $id
 * @property string $owner_type
 * @property string $owner_id
 * @property int $owner_seq
 * @property string $finance_transaction_id
 * @property string $amount
 * @property string $currency
 * @property string $state
 * @property string|null $transaction_checksum
 * @property string|null $previous_hash
 * @property string $hash
 * @property Carbon $created_at
 */
class FinanceChainEntry extends Model
{
    public const ID = 'id';
    public const OWNER_TYPE = 'owner_type';
    public const OWNER_ID = 'owner_id';
    public const OWNER_SEQ = 'owner_seq';
    public const FINANCE_TRANSACTION_ID = 'finance_transaction_id';
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';
    public const STATE = 'state';
    public const TRANSACTION_CHECKSUM = 'transaction_checksum';
    public const PREVIOUS_HASH = 'previous_hash';
    public const HASH = 'hash';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    /*
     * Owner used for transactions that never got a wallet (legacy orphans)
     */
    public const UNOWNED = '__none__';

    protected $table = 'finance_chain_entries';

    public $timestamps = false;

    protected $guarded = [self::ID];

    protected $casts = [
        self::OWNER_SEQ => 'integer',
        self::AMOUNT => 'decimal:5',
        self::CREATED_AT => 'datetime',
    ];

    public function payload(): array
    {
        return [
            $this->{self::OWNER_TYPE},
            $this->{self::OWNER_ID},
            $this->{self::OWNER_SEQ},
            $this->{self::FINANCE_TRANSACTION_ID},
            $this->{self::AMOUNT},
            $this->{self::CURRENCY},
            $this->{self::STATE},
            $this->{self::TRANSACTION_CHECKSUM},
            $this->{self::PREVIOUS_HASH},
            $this->{self::CREATED_AT}?->format('Y-m-d H:i:s'),
        ];
    }

    public function verifyHash(): bool
    {
        return Signer::verify($this->payload(), $this->{self::HASH});
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, self::FINANCE_TRANSACTION_ID);
    }
}
