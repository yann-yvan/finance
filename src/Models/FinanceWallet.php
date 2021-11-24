<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;

class FinanceWallet extends Model
{
    protected $fillable = ['id', 'owner_id', 'credit_wallet_id', 'transaction_id'];
}