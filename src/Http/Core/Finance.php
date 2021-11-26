<?php


namespace NYCorp\Finance\Http\Core;


use Exception;
use NYCorp\Finance\FinanceServiceProvider;

class Finance
{
    const FINANCE_CONFIG_NAME = "finance";

    public static function getFinanceAccount(){
        $account = config('auth.providers.users.model')::find(\request()->get(config(self::FINANCE_CONFIG_NAME . ".finance_account_id_parameter")));
        if (empty($account)) {
            $account = auth()->user();
            if (empty($account)) {
                throw new Exception("Finance Account not found");
            }
        }
        return $account;
    }
}