<?php


namespace NYCorp\Finance\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use NYCorp\Finance\Casts\TransactionDisplayCast;
use NYCorp\Finance\Exceptions\CompromisedTransactionException;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Exceptions\LockedTransactionException;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Http\Core\ExchangeRate;
use NYCorp\Finance\Integrity\AuditTrail;
use NYCorp\Finance\Integrity\FinanceSchema;
use NYCorp\Finance\Integrity\HashChain;
use NYCorp\Finance\Integrity\Signer;
use NYCorp\Finance\Http\Response\ResponseCode;

/**
 * Class FinanceTransaction
 *
 * @property string $id
 * @property float $amount
 * @property string $currency
 * @property Carbon|null $verify_at
 * @property array $start_log
 * @property array|null $end_log
 * @property string|null $external_id
 * @property string $start_signature
 * @property string $description
 * @property TransactionDisplay|null $display public, translatable description
 * @property bool $is_locked
 * @property string|null $checksum
 * @property string $state
 * @property int $finance_provider_id
 * @property int $signature_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property FinanceProvider $finance_provider
 * @property FinanceWallet|null $finance_wallet
 *
 * @package App\Models\Base
 */
class FinanceTransaction extends Model
{
    // Constant definitions for column names
    public const STATE_PENDING = "PENDING";
    public const STATE_COMPLETED = "COMPLETED";
    public const STATE_FAILED = "FAILED";
    public const STATE_CANCELED = "CANCELED";

    public const DEPOSIT_MOVEMENT = "deposit";
    public const WITHDRAWAL_MOVEMENT = "withdrawal";
    public const TRANSFER_MOVEMENT = "transfer";


    public const ID = 'id';
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';
    public const VERIFY_AT = 'verify_at';
    public const START_LOG = 'start_log';
    public const END_LOG = 'end_log';
    public const EXTERNAL_ID = 'external_id';
    public const START_SIGNATURE = 'start_signature';
    public const DESCRIPTION = 'description';
    public const DISPLAY = 'display';
    public const IS_LOCKED = 'is_locked';
    public const STATE = 'state';
    public const CHECKSUM = 'checksum';
    public const FINANCE_PROVIDER_ID = 'finance_provider_id';
    public const SIGNATURE_VERSION = 'signature_version';

    /*
     *  Primary key is not auto-incrementing
     */
    public $incrementing = false;
    protected $keyType = 'string';


    protected $hidden = [
        self::START_SIGNATURE,
        self::START_LOG,
        self::END_LOG,
        self::CHECKSUM
    ];

    /*
     * Attributes that are mass assignable
     */
    protected $fillable = [
        self::ID,
        self::AMOUNT,
        self::START_LOG,
        self::START_SIGNATURE,
        self::CURRENCY,
        self::DESCRIPTION,
        self::DISPLAY,
        self::STATE,
        self::SIGNATURE_VERSION,
        self::FINANCE_PROVIDER_ID,
    ];

    /*
     * Casts for data types
     */
    protected $casts = [
        self::AMOUNT => 'decimal:6',
        self::VERIFY_AT => 'datetime',
        self::START_LOG => 'json',
        self::END_LOG => 'json',
        self::DISPLAY => TransactionDisplayCast::class,
    ];

    /**
     * States for the enum field
     * @return string[]
     */
    public static function getStates(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_COMPLETED,
            self::STATE_FAILED,
            self::STATE_CANCELED
        ];
    }

    // Before saving the transaction, generate the appropriate checksum
    protected static function boot()
    {
        parent::boot();

        static::creating(static function (FinanceTransaction $model) {
            $model->id = Str::uuid()->toString(); // Generate UUID as a string for ID
            $model->state = self::STATE_PENDING;

            if (FinanceSchema::hasColumn($model->getTable(), self::SIGNATURE_VERSION)) {
                // v3 (HMAC with a dedicated key) when a key is configured, v2 otherwise
                $model->signature_version = Signer::isEnabled() ? 3 : 2;
            }

            // Generate the start signature when creating the transaction
            $model->generateStartSignature();
        });

        // Hook into saving event to verify checksum and lock transactions
        static::updating(static function ($model) {
            if ($model->is_locked) {
                throw new LockedTransactionException(code: ResponseCode::REQUEST_NOT_AUTHORIZED, message: "This transaction {$model->id} is locked and cannot be updated.");
            }

            // Verify start signature before updating
            if ($model->isDirty(self::STATE) && $model->requiresFinalization()) {
                $model->verifyStartSignature();  // Verify the integrity of the start signature
                $model->generateChecksum();  // Generate the checksum after verification
                $model->is_locked = true;  // Lock the transaction once it's completed or finalized
            }
        });

        // Seal every finalized transaction in the owner's hash chain and the audit log
        static::updated(static function (FinanceTransaction $model) {
            if ($model->wasChanged(self::STATE) && $model->is_locked && $model->requiresFinalization()) {
                HashChain::append($model);
                AuditTrail::record(
                    FinanceAuditLog::EVENT_TRANSACTION_FINALIZED,
                    $model,
                    [self::STATE => $model->getOriginal(self::STATE)],
                    [self::STATE => $model->state, self::EXTERNAL_ID => $model->external_id, self::AMOUNT => (string)$model->amount, self::CURRENCY => $model->currency],
                );
            }
        });
    }

    public function generateStartSignature(): void
    {
        $this->start_signature = $this->calculateStartSignature();
    }

    public function calculateStartSignature(): string
    {
        $version = (int)($this->signature_version ?? 1);

        if ($version === 3) {
            return Signer::sign($this->getStartSignatureFieldsV3());
        }

        if ($version === 2) {
            $fields = $this->getStartSignatureFieldsV2();
        } else {
            $fields = $this->getStartSignatureFieldsV1();
        }

        return $this->generateHash($fields);
    }

    /**
     * v3: the values (not the column names), serialised without ambiguity and signed with a key
     *
     * @return array
     */
    protected function getStartSignatureFieldsV3(): array
    {
        return [
            $this->{self::ID},
            $this->{self::AMOUNT},
            $this->{self::START_LOG},
            $this->{self::CURRENCY},
            $this->{self::DESCRIPTION},
            $this->{self::FINANCE_PROVIDER_ID},
            $this->{self::DISPLAY}?->toArray(),
            3,
        ];
    }

    /**
     * Get the fields to be included in the start signature generation
     *
     * @return array
     */
    protected function getStartSignatureFieldsV2(): array
    {
        return [
            $this->{self::ID},
            $this->{self::AMOUNT},
            json_encode($this->{self::START_LOG}),
            #$this->getOriginal(self::STATE) ?? $this->{self::STATE},
            $this->{self::CURRENCY},
            $this->{self::DESCRIPTION},
            $this->{self::FINANCE_PROVIDER_ID},
        ];
    }

    /**
     * Get the fields to be included in the start signature generation
     *
     * @return array
     * @deprecated v1 hashes the column names, not their values: it proves nothing and is only kept to read old rows
     */
    protected function getStartSignatureFieldsV1(): array
    {
        return [
            self::ID,
            self::AMOUNT,
            self::START_LOG,
            self::STATE,
            self::CURRENCY,
            self::DESCRIPTION,
            self::FINANCE_PROVIDER_ID,
        ];
    }

    /**
     * Generate a hash checksum from a list of fields
     *
     * @param array $fields
     * @return string
     */
    protected function generateHash(array $fields): string
    {
        // Generate the checksum using SHA-256 or any other preferred hash algorithm
        return hash('sha256', implode('|', $fields));
    }

    /**
     * Determine if the state requires finalization (completion, failure, or cancellation)
     *
     * @return bool
     */
    public function requiresFinalization(): bool
    {
        return in_array($this->state, [self::STATE_COMPLETED, self::STATE_FAILED, self::STATE_CANCELED], true);
    }

    /**
     * Verify the start signature's integrity
     *
     * @throws \Exception
     */
    public function verifyStartSignature(): void
    {
        if (!$this->hasValidStartSignature()) {
            throw new CompromisedTransactionException(code: ResponseCode::REQUEST_VALIDATION_ERROR, message: 'Start signature has been tampered with.');
        }
    }

    public function hasValidStartSignature(): bool
    {
        if ((int)($this->signature_version ?? 1) === 3) {
            return Signer::verify($this->getStartSignatureFieldsV3(), $this->start_signature);
        }

        return is_string($this->start_signature) && hash_equals($this->calculateStartSignature(), $this->start_signature);
    }

    /**
     * Generate the checksum to validate the entire transaction (both start and end)
     */
    public function generateChecksum(): void
    {
        $this->checksum = $this->calculateChecksum();
    }

    /**
     * Generate the checksum to validate the entire transaction (both start and end)
     */
    public function calculateChecksum(): string
    {
        $version = (int)($this->signature_version ?? 1);

        if ($version === 3) {
            return Signer::sign($this->getChecksumFieldsV3());
        }

        $startFields = $version === 2 ? $this->getStartSignatureFieldsV2() : $this->getStartSignatureFieldsV1();

        $endFields = $version === 2 ? $this->getEndSignatureFieldsV2() : $this->getEndSignatureFieldsV1();

        return $this->generateHash(array_merge($startFields, $endFields));
    }

    protected function getChecksumFieldsV3(): array
    {
        return array_merge($this->getStartSignatureFieldsV3(), [
            $this->{self::START_SIGNATURE},
            $this->{self::STATE},
            $this->{self::END_LOG},
            $this->{self::VERIFY_AT}?->format('Y-m-d H:i:s'),
            $this->{self::EXTERNAL_ID},
        ]);
    }

    /**
     * Get the fields to be included in the checksum calculation for end of transaction
     *
     * @return array
     */
    protected function getEndSignatureFieldsV2(): array
    {
        return [
            $this->{self::START_SIGNATURE},
            $this->{self::STATE},
            json_encode($this->{self::END_LOG}),
            $this->{self::VERIFY_AT},
            $this->{self::EXTERNAL_ID},
        ];
    }

    /**
     * Get the fields to be included in the checksum calculation for end of transaction
     *
     * @return array
     * @deprecated see getStartSignatureFieldsV1()
     */
    protected function getEndSignatureFieldsV1(): array
    {
        return [
            self::END_LOG,
            self::VERIFY_AT,
            self::EXTERNAL_ID,
        ];
    }

    /**
     * Verify the current checksum against the stored checksum.
     */
    public function verifyChecksum(): bool
    {
        if ((int)($this->signature_version ?? 1) === 3) {
            return Signer::verify($this->getChecksumFieldsV3(), $this->{self::CHECKSUM});
        }

        return is_string($this->{self::CHECKSUM}) && hash_equals($this->calculateChecksum(), $this->{self::CHECKSUM});
    }

    public function displayKey(): ?string
    {
        return $this->{self::DISPLAY}?->key();
    }

    public function displayParams(): array
    {
        return $this->{self::DISPLAY}?->params() ?? [];
    }

    /**
     * Public text, translated with the host application's lang files; falls back to the technical description.
     */
    public function describe(?string $locale = null): string
    {
        return $this->{self::DISPLAY}?->translate($locale) ?? (string)$this->{self::DESCRIPTION};
    }

    public function tags(): HasMany
    {
        return $this->hasMany(FinanceTransactionTag::class, FinanceTransactionTag::FINANCE_TRANSACTION_ID);
    }

    /**
     * Add tags (also on a finalized transaction: tags are for search, not part of any signature).
     *
     * @throws FinanceException on an invalid tag
     */
    public function tag(array|string ...$tags): static
    {
        $tags = FinanceTransactionTag::normalize(array_merge(...array_map(static fn($tag) => (array)$tag, $tags)));
        if (empty($tags) || !FinanceSchema::hasTable('finance_transaction_tags')) {
            return $this;
        }

        $now = now();
        FinanceTransactionTag::insertOrIgnore(array_map(fn($tag) => [
            FinanceTransactionTag::FINANCE_TRANSACTION_ID => $this->getKey(),
            FinanceTransactionTag::TAG => $tag,
            FinanceTransactionTag::CREATED_AT => $now,
        ], $tags));
        $this->unsetRelation('tags');

        return $this;
    }

    public function untag(array|string ...$tags): static
    {
        $tags = FinanceTransactionTag::normalize(array_merge(...array_map(static fn($tag) => (array)$tag, $tags)));
        if (!empty($tags) && FinanceSchema::hasTable('finance_transaction_tags')) {
            $this->tags()->whereIn(FinanceTransactionTag::TAG, $tags)->delete();
            $this->unsetRelation('tags');
        }
        return $this;
    }

    public function tagList(): array
    {
        return $this->tags->pluck(FinanceTransactionTag::TAG)->sort()->values()->all();
    }

    /**
     * Transactions carrying all the given tags
     */
    public function scopeTagged(Builder $query, array|string ...$tags): Builder
    {
        $tags = FinanceTransactionTag::normalize(array_merge(...array_map(static fn($tag) => (array)$tag, $tags)));
        if (empty($tags)) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn(self::ID), static function ($sub) use ($tags) {
            $sub->select(FinanceTransactionTag::FINANCE_TRANSACTION_ID)
                ->from('finance_transaction_tags')
                ->whereIn(FinanceTransactionTag::TAG, $tags)
                ->groupBy(FinanceTransactionTag::FINANCE_TRANSACTION_ID)
                ->havingRaw('COUNT(DISTINCT ' . FinanceTransactionTag::TAG . ') = ?', [count($tags)]);
        });
    }

    /**
     * Transactions carrying at least one of the given tags
     */
    public function scopeTaggedAny(Builder $query, array|string ...$tags): Builder
    {
        $tags = FinanceTransactionTag::normalize(array_merge(...array_map(static fn($tag) => (array)$tag, $tags)));
        if (empty($tags)) {
            return $query;
        }

        return $query->whereExists(function ($sub) use ($tags) {
            $sub->selectRaw(1)
                ->from('finance_transaction_tags')
                ->whereColumn('finance_transaction_tags.' . FinanceTransactionTag::FINANCE_TRANSACTION_ID, $this->qualifyColumn(self::ID))
                ->whereIn('finance_transaction_tags.' . FinanceTransactionTag::TAG, $tags);
        });
    }

    public function financeProvider(): BelongsTo
    {
        return $this->belongsTo(FinanceProvider::class, self::FINANCE_PROVIDER_ID);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(FinanceWallet::class);
    }

    public function getConvertedAmount(bool $asInt = false): float
    {
        $amount = abs($this->amount) * Arr::get($this->start_log, 'parameters.exchange_rate.value', 1);
        if ($asInt) {
            return ceil($amount);
        }
        return ExchangeRate::round($amount);
    }

    public function getSourceRateAmount(float $amount): float
    {
        return ExchangeRate::round($amount * Arr::get($this->start_log, 'parameters.exchange_rate.value', 1));
    }

    public function scopeCredit($query)
    {
        return $query->where('amount', '>=', 0);
    }

    public function scopeDebit($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeNotDefaultProvider($query)
    {
        return $query->whereHas('financeProvider', fn($query) => $query->where('assigned_id', '<>', ConfigReader::getDefaultPaymentProviderId()));
    }
}