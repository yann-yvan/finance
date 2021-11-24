<?php


namespace NYCorp\Finance\Http\Controllers;


use Illuminate\Http\Request;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Http\ResponseParser\DefResponse;
use NYCorp\Finance\Models\FinanceTransaction;

class FinanceController extends Controller
{
    public function deposit(Request $request)
    {
        $transactionResponse = new DefResponse(FinanceTransactionController::deposit($request));
        if ($transactionResponse->isSuccess()) {
            $walletResponse = new DefResponse(FinanceWalletController::build(auth()->user(), $transactionResponse->getData()));
            if (!$walletResponse->isSuccess()) {
                return $walletResponse->getResponse();
            }
            PaymentProviderGateway::load($transactionResponse->getData()["finance_provider_id"])->deposit(FinanceTransaction::find($transactionResponse->getData()["id"]));
        }
        return $transactionResponse->getResponse();
    }

    public function withdrawal(Request $request)
    {
        $transactionResponse = new DefResponse(FinanceTransactionController::withdrawal($request));
        if ($transactionResponse->isSuccess()) {
            $walletResponse = new DefResponse(FinanceWalletController::build(auth()->user(), $transactionResponse->getData()));
            if (!$walletResponse->isSuccess()) {
                return $walletResponse->getResponse();
            }
            PaymentProviderGateway::load($transactionResponse->getData()["finance_provider_id"])->withdrawal(FinanceTransaction::find($transactionResponse->getData()["id"]));
        }
        return $transactionResponse->getResponse();
    }

    public function depositValidation(Request $request)
    {

    }

}