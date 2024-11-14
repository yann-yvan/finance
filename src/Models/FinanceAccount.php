<?php

namespace NYCorp\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    // Table name
    protected $table = 'finance_accounts';

    // Constant definitions for column names
    public const ID = 'id';
    public const CREDIBILITY = 'credibility';
    public const LAST_VERIFICATION_AT = 'last_verification_at';
    public const IS_ACCOUNT_ACTIVE = 'is_account_active';
    public const ACCOUNT_LOGS = 'account_logs';
    public const OWNER_ID = 'owner_id';
    public const OWNER_TYPE = 'owner_type';
    public const THRESHOLD = 'threshold';

    // Attributes that are mass assignable
    protected $fillable = [
        self::CREDIBILITY,
        self::LAST_VERIFICATION_AT,
        self::IS_ACCOUNT_ACTIVE,
        self::ACCOUNT_LOGS,
        self::OWNER_ID,
        self::OWNER_TYPE
    ];

    // Casts for data types
    protected $casts = [
        self::CREDIBILITY => 'decimal:6',
        self::LAST_VERIFICATION_AT => 'datetime',
        self::IS_ACCOUNT_ACTIVE => 'boolean',
        self::ACCOUNT_LOGS => 'json',
    ];

    // Check if the current balance is above the threshold
    public function isBalanceAboveThreshold(): bool
    {
        return is_null($this->threshold) || $this->balance >= $this->threshold;
    }
}