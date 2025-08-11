<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use NYCorp\Finance\Scope\InvalidWalletScope;

class FinanceWallet extends Model
{
    public const ID = 'id';
    public const OWNER_ID = 'owner_id';

    public const OWNER_TYPE = 'owner_type';

    public const CHECKSUM = 'checksum';

    public const FINANCE_TRANSACTION_ID = 'finance_transaction_id';

    // Constants for the field names
    public const TRANSFER_FROM_WALLET_ID = 'transfer_from_wallet_id';


    public $incrementing = false;

    /*
     * Table name
     */
    protected $table = 'finance_wallets';
    /*
   * Primary key type is string, not an integer
   */
    protected $keyType = 'string';

    protected $fillable = [
        self::ID,
        self::OWNER_ID,
        self::OWNER_TYPE,
        self::CHECKSUM,
        self::FINANCE_TRANSACTION_ID,
        self::TRANSFER_FROM_WALLET_ID,
    ];

    // Fields that can be mass-assigned

    protected static function boot()
    {
        parent::boot();

        //Exclude all wallet with unsuccessfully transaction
        static::addGlobalScope(new InvalidWalletScope());

        static::creating(static function ($model) {
            $model->id = Str::uuid(); // Generate UUID as a string for ID
        });

        // Automatically set the checksum when saving the model.
        static::saving(static function ($wallet) {
            $wallet->{self::CHECKSUM} = $wallet->calculateChecksum();
        });
    }

    /**
     * Calculate a checksum for the current state of the wallet.
     *
     * This could include sensitive fields or fields that indicate wallet status.
     */
    public function calculateChecksum(): string
    {
        $data = [
            $this->{self::ID},
            $this->{self::OWNER_ID},
            $this->{self::OWNER_TYPE},
            $this->{self::TRANSFER_FROM_WALLET_ID},
            $this->{self::FINANCE_TRANSACTION_ID},
        ];

        // Generate the checksum using SHA-256 or any other preferred hash algorithm
        return hash('sha256', implode('|', $data));
    }

    /**
     * The transaction related to the wallet.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, self::FINANCE_TRANSACTION_ID);
    }

    /**
     * The owner related to the wallet.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function finance_account(): HasOne
    {
        return $this->hasOne(FinanceAccount::class, FinanceAccount::OWNER_ID, FinanceWallet::OWNER_ID)->where(FinanceAccount::OWNER_TYPE, __CLASS__);
    }

    /**
     * The wallet that transferred money to this wallet (if applicable).
     */
    public function transferFromWallet(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, self::TRANSFER_FROM_WALLET_ID);
    }

    /**
     * Check if the wallet has received money from another wallet.
     */
    public function isTransfer(): bool
    {
        return $this->{self::TRANSFER_FROM_WALLET_ID} !== null;
    }

    /**
     * Verify the current checksum against the stored checksum.
     */
    public function verifyChecksum(): bool
    {
        return $this->{self::CHECKSUM} === $this->calculateChecksum();
    }
}