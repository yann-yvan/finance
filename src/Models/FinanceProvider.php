<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;

class FinanceProvider extends Model
{
    protected $fillable = ["assigned_id", "name", "is_withdrawal_available", "is_available", "is_deposit_available"];

    public function exist(): bool
    {
        return !empty($this->assigned_id);
    }

    public function isAvailable(): bool
    {
        return $this->is_available;
    }

    public function isWithdrawalAvailable(): bool
    {
        return $this->is_withdrawal_available;
    }

    public function isDepositAvailable(): bool
    {
        return $this->is_deposit_available;
    }
}