<?php


namespace NYCorp\Finance\Http\Core;


use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Finance
{
    public const FINANCE_CONFIG_NAME = "finance";

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getFinanceAccount(){
        $account = config('auth.providers.users.model')::find(\request()->get(config(self::FINANCE_CONFIG_NAME . ".finance_account_id_parameter")));
        if (empty($account)) {
            $account = auth()->user();
            if ($account === null) {
                throw new Exception("Finance Account not found");
            }
        }
        return $account;
    }
}