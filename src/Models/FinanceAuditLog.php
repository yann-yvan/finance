<?php

namespace NYCorp\Finance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use NYCorp\Finance\Integrity\Signer;

/**
 * Append-only, signed audit log row.
 *
 * @property int $id
 * @property string $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $context
 * @property string $hash
 * @property Carbon $created_at
 */
class FinanceAuditLog extends Model
{
    public const ID = 'id';
    public const EVENT = 'event';
    public const SUBJECT_TYPE = 'subject_type';
    public const SUBJECT_ID = 'subject_id';
    public const OLD_VALUES = 'old_values';
    public const NEW_VALUES = 'new_values';
    public const ACTOR_TYPE = 'actor_type';
    public const ACTOR_ID = 'actor_id';
    public const CONTEXT = 'context';
    public const HASH = 'hash';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    public const EVENT_TRANSACTION_FINALIZED = 'transaction.finalized';
    public const EVENT_CALLBACK_REJECTED = 'callback.rejected';
    public const EVENT_ACCOUNT_RECALCULATED = 'account.recalculated';
    public const EVENT_ACCOUNT_THRESHOLD_CHANGED = 'account.threshold_changed';
    public const EVENT_INTEGRITY_VERIFIED = 'integrity.verified';
    public const EVENT_HISTORY_SEALED = 'integrity.history_sealed';

    protected $table = 'finance_audit_logs';

    public $timestamps = false;

    protected $guarded = [self::ID];

    protected $casts = [
        self::OLD_VALUES => 'json',
        self::NEW_VALUES => 'json',
        self::CREATED_AT => 'datetime',
    ];

    public function payload(): array
    {
        return [
            $this->{self::EVENT},
            $this->{self::SUBJECT_TYPE},
            $this->{self::SUBJECT_ID},
            $this->{self::OLD_VALUES},
            $this->{self::NEW_VALUES},
            $this->{self::ACTOR_TYPE},
            $this->{self::ACTOR_ID},
            $this->{self::CONTEXT},
            $this->{self::CREATED_AT}?->format('Y-m-d H:i:s'),
        ];
    }

    public function verifyHash(): bool
    {
        return Signer::verify($this->payload(), $this->{self::HASH});
    }
}
