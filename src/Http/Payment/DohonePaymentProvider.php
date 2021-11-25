<?php


namespace NYCorp\Finance\Http\Payment;


use Dohone\Facades\DohonePayIn;
use Exception;
use Illuminate\Http\Request;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Traits\FinanceProviderTrait;
use NYCorp\Finance\Traits\PaymentProviderTrait;

class DohonePaymentProvider extends PaymentProviderGateway
{
    use PaymentProviderTrait;
    use FinanceProviderTrait;

    public function getId(): int
    {
        return 3;
    }

    public function getName(): string
    {
        return "Dohone";
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $api = DohonePayIn::payWithAPI()
            ->setAmount($transaction->amount)
            ->setClientPhone(request()->get('phone'))
            ->setClientEmail($transaction->wallet->owner->email)
            //->setClientName("$user->first_name $user->last_name")
            ->setCommandID($transaction->id)
            //->setNotifyPage(route('dohone.callback', ['ref' => $transaction->getRouteKey()]),)
            ->setOTPCode(request()->get('otp'))
            ->setDescription($transaction->description)
            ->setMethod(request()->get('mode'));
        //$api->setClientID(auth()->id());
        $result = $api->get();
        $this->successful = $result->isSuccess();
        $this->response = $this->successful ? $result->getMessage() : $result->getErrors();
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        // TODO: Implement withdrawal() method.
    }

    /**
     * @OA\Post(
     *    path="/api/user/wallet/mobile-sms-verify",
     *   tags={"Wallet"},
     *   summary="MObile money sms verification",
     *   description="",
     *   operationId="MomoVerify",
     *   @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="sms code",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *   @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="sms code receiver number",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *     @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="json"),
     *
     *   ),
     * )
     * @param Request $request
     *
     * @return array|JsonResponse
     */
    public function SMSConfirmation(Request $request)
    {
        $response = DohonePayIn::sms()
            ->setCode($request->code)
            ->setPhone($request->phone)
            ->get();

        return $this->liteResponse($response->isSuccess() ? config('code.request.SUCCESS') : config('code.request.FAILURE'), $response->getMessage());
    }

    public function payOut($transaction, $user)
    {

    }

    public function getAccount($userId = null)
    {
        return config("dohone.merchantToken");
    }

    public function onSuccess(Request $request)
    {
        $transaction = FinanceTransaction::find($request->ref);
        if (empty($transaction))
            return $this->liteResponse(config('code.request.FAILURE'), null, "we can't found this order");

        $data = $request->all();
        try {
            //file_put_contents("confidential/" . $transaction->id . ".json", ["transaction" => $transaction, "data" => json_encode($data)]);
            if ($request->hash == md5($data["idReqDoh"] . $data["rI"] . $data["rMt"] . config("dohone.payOutHashCode"))) {
                //  FcmController::paymentAlert($user, $model, $context);

                $transaction->payment_token = $data['idReqDoh'];
                $transaction->account = config('dohone.start.rH');
                $transaction->verification_log = json_encode($request->all());
                //TransactionController::verify($transaction);

                //file_put_contents("confidential/" . $transaction->id . "-verify.json", ["transaction" => $transaction, "data" => json_encode($data)]);
                //$transaction->notify(new TransactionAlert($transaction, true));
                return $this->liteResponse(config('code.request.SUCCESS'));
            }
            return $this->liteResponse(config('code.request.FAILURE'));
        } catch (Exception $exception) {
            //file_put_contents("confidential/exception-".$transaction->id.".md", $exception->getMessage());
            return $this->liteResponse(config('code.request.FAILURE'), null, $exception->getMessage());
        }
    }

}