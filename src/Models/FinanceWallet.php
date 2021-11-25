<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;
use NYCorp\Finance\Scope\InvalidWalletScope;

class FinanceWallet extends Model
{
    protected $fillable = ['id', 'owner_id', 'credit_wallet_id', 'finance_transaction_id'];

    protected static function boot()
    {
        parent::boot();

        //Exclude all wallet with unsuccessfully transaction
        static::addGlobalScope(new InvalidWalletScope());
    }

    public function owner()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function transaction(){
       return $this->belongsTo("NYCorp\Finance\Models\FinanceTransaction","finance_transaction_id");
    }
}