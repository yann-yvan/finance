<?php


namespace NYCorp\Finance\Models;


use Illuminate\Database\Eloquent\Model;

class FinanceTransaction extends Model
{
    const STATE_PENDING = "PENDING";
    const STATE_SUCCESS = "SUCCESS";
    const STATE_FAILED = "FAILED";
    public $incrementing = false;
    protected $fillable = ['amount', 'id', 'start_log', 'description', 'start_signature', 'finance_provider_id', 'state'];
    protected $hidden = ["start_signature", "start_log", "end_log", "end_signature"];

    public static function getStates(): array
    {
        return [FinanceTransaction::STATE_PENDING, FinanceTransaction::STATE_SUCCESS, FinanceTransaction::STATE_FAILED];
    }

    public function wallet(){
        return $this->hasOne("NYCorp\Finance\Models\FinanceWallet");
    }
}