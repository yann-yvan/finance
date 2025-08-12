<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;

class FinanceProvider extends Model
{
    // Constant definitions for column names
    public const ID = 'id';
    public const NAME = 'name';
    public const ASSIGNED_ID = 'assigned_id';
    public const LOGO = 'logo';
    public const COLOR = 'color';
    public const IS_PUBLIC = 'is_public';
    public const IS_AVAILABLE = 'is_available';
    public const IS_DEPOSIT_AVAILABLE = 'is_deposit_available';
    public const IS_WITHDRAWAL_AVAILABLE = 'is_withdrawal_available';

    // Attributes that are mass assignable
    protected $fillable = [
        self::NAME,
        self::ASSIGNED_ID,
        self::LOGO,
        self::COLOR,
        self::IS_PUBLIC,
        self::IS_AVAILABLE,
        self::IS_DEPOSIT_AVAILABLE,
        self::IS_WITHDRAWAL_AVAILABLE,
    ];

    // Casts for data types
    protected $casts = [
        self::IS_PUBLIC => 'boolean',
        self::IS_AVAILABLE => 'boolean',
        self::IS_DEPOSIT_AVAILABLE => 'boolean',
        self::IS_WITHDRAWAL_AVAILABLE => 'boolean',
    ];

    public function isAvailable(): bool
    {
        return $this->{self::IS_AVAILABLE};
    }

    public function isWithdrawalAvailable(): bool
    {
        return $this->{self::IS_WITHDRAWAL_AVAILABLE};
    }

    public function isDepositAvailable(): bool
    {
        return $this->{self::IS_DEPOSIT_AVAILABLE};
    }

    public function scopeAvailable(Builder $builder): Builder
    {
        return $builder->where('is_available', true);
    }

    public function scopePublic(Builder $builder): Builder
    {
        return $builder->where('is_public', true);
    }

    public function scopeCanCollect(Builder $builder): Builder
    {
        return $builder->where('is_deposit_available', true);
    }

    public function scopeCanWithdraw(Builder $builder): Builder
    {
        return $builder->where('is_withdrawal_available', true);
    }

    public function toGateway(): PaymentProviderGateway
    {
        return PaymentProviderGateway::load(id: $this->getKey());
    }
}