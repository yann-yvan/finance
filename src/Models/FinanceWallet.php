<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;

class FinanceWallet extends Model
{
    protected $fillable = ['id', 'owner_id', 'credit_wallet_id', 'finance_transaction_id'];

    public function owner(){
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}