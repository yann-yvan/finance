<?php

/**
 * Created by Reliese Model.
 */

namespace NYCorp\Finance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Traits\FinanceAccountTrait;

/**
 * Class FinanceLedger
 *
 * @property string $id
 * @property string $owner_id
 * @property string $owner_type
 * @property string $checksum
 * @property string|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models\Base
 */
class FinanceLedger extends Model
{
    use SoftDeletes;
    use FinanceAccountTrait;

    const ID = 'id';
    const NAME = 'name';
    const CURRENCY = 'currency';
    const OWNER_ID = 'owner_id';
    const OWNER_TYPE = 'owner_type';
    const CHECKSUM = 'checksum';
    const DELETED_AT = 'deleted_at';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    public $incrementing = false;

    protected $table = 'finance_ledgers';

    protected $casts = [
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime'
    ];

    protected $fillable = [
        self::NAME,
        self::CURRENCY,
        self::OWNER_ID,
        self::OWNER_TYPE,
        self::CHECKSUM
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(static function ($model) {
            $model->id = Str::uuid(); // Generate UUID as a string for ID
        });

        // Automatically set the checksum when saving the model.
        static::saving(static function ($wallet) {
            $wallet->{self::CHECKSUM} = $wallet->calculateChecksum();
            if ($wallet->{self::CURRENCY} === null) {
                $wallet->{self::CURRENCY} = (new FinanceLedger())->getCurrency();
            }
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
            $this->{self::NAME},
            $this->{self::OWNER_ID},
            $this->{self::OWNER_TYPE}
        ];

        // Generate the checksum using SHA-256 or any other preferred hash algorithm
        return hash('sha256', implode('|', $data));
    }

    public function getCurrency(): string
    {
        return $this->{self::CURRENCY} ?? ConfigReader::getDefaultCurrency();
    }
}
