<?php


namespace NYCorp\Finance\Http\Controllers;


use NYCorp\Finance\Http\Core\Finance;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceProvider;

class FinanceProviderController extends Controller
{
    public function providers()
    {
        try {
            PaymentProviderGateway::load();
            return $this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.SUCCESS'), FinanceProvider::all(["assigned_id", "name"]));
        } catch (\Exception $exception) {
            return $this->respondError($exception);
        }
    }
}