<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use NYCorp\Finance\Exceptions\CompromisedTransactionException;
use NYCorp\Finance\Exceptions\LockedTransactionException;
use Nycorp\LiteApi\Models\ResponseCode;

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
    public const IS_LOCKED = 'is_locked';
    public const STATE = 'state';
    public const CHECKSUM = 'checksum';
    public const FINANCE_PROVIDER_ID = 'finance_provider_id';

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
        self::STATE,
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
            $model->id = Str::uuid(); // Generate UUID as a string for ID
            $model->state = self::STATE_PENDING;

            // Generate the start signature when creating the transaction
            $model->generateStartSignature();
        });

        // Hook into saving event to verify checksum and lock transactions
        static::saving(static function ($model) {
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
    }

    /**
     * Generate the start signature based on the initial fields of the transaction
     */
    public function generateStartSignature(): void
    {
        $fields = $this->getStartSignatureFields();
        $this->start_signature = $this->generateHash($fields);
    }

    /**
     * Get the fields to be included in the start signature generation
     *
     * @return array
     */
    protected function getStartSignatureFields(): array
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
        $fields = $this->getStartSignatureFields();
        $expectedSignature = $this->generateHash($fields);

        if ($this->start_signature !== $expectedSignature) {
            throw new CompromisedTransactionException(code: ResponseCode::REQUEST_VALIDATION_ERROR, message: 'Start signature has been tampered with.');
        }
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
        $fields = array_merge($this->getStartSignatureFields(), $this->getEndSignatureFields());
        return $this->generateHash($fields);
    }



    /**
     * Get the fields to be included in the checksum calculation for end of transaction
     *
     * @return array
     */
    protected function getEndSignatureFields(): array
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
        return $this->{self::CHECKSUM} === $this->calculateChecksum();
    }

    public function financeProvider(): BelongsTo
    {
        return $this->belongsTo(FinanceProvider::class, self::FINANCE_PROVIDER_ID);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(FinanceWallet::class);
    }
}