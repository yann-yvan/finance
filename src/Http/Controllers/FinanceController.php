<?php


namespace NYCorp\Finance\Http\Controllers;




use Illuminate\Http\Request;
use NYCorp\Finance\Http\ResponseParser\DefResponse;

class FinanceController extends Controller
{
    public function deposit(Request $request){
        $transactionResponse = new DefResponse(FinanceTransactionController::build($request));
        if ($transactionResponse->isSuccess()) {
            $walletResponse = new DefResponse(FinanceWalletController::build(auth()->user(),$transactionResponse->getData()));
            if (!$walletResponse->isSuccess()) {
                return $walletResponse->getResponse();
            }
        }
        return $transactionResponse->getResponse();
    }

    public function withdrawal(Request $request){

    }

    public function depositValidation(Request $request){

    }

}