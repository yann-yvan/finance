<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;

class FinanceProvider extends Model
{
    protected $fillable = ["name", "is_withdrawal_available", "is_available", "is_deposit_available"];
}