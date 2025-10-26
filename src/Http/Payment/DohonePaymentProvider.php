<?php


namespace NYCorp\Finance\Http\Payment;


use Dohone\PayIn\DohonePayIn;
use Dohone\PayOut\DohonePayOut;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;

class DohonePaymentProvider extends PaymentProviderGateway
{
    protected bool $isWithdrawalRealTime = true;

    public static function getId(): string
    {
        return "DHN";
    }

    public static function getName(): string
    {
        return "Dohone";
    }

    public static function getCurrency(): string
    {
        return 'XAF';
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $api = DohonePayIn::payWithAPI()
            ->setAmount($transaction->getConvertedAmount(asInt: true))
            ->setClientPhone(request()->get('phone'))
            #->setClientEmail(Finance::getFinanceAccount()->{config(Finance::FINANCE_CONFIG_NAME . ".user_email_field")})
            //->setClientName("$user->first_name $user->last_name")
            ->setCommandID($transaction->id)
            ->setNotifyPage($this->depositNotificationUrl())
            ->setOTPCode(request()->get('otp'))
            ->setDescription($transaction->description)
            ->setMethod(request()->get('mode'));
        //$api->setClientID(auth()->id());
        $result = $api->get();
        $this->successful = $result->isSuccess();
        $this->message = $result->getMessage();
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $result->getErrors(), $result->shouldVerifySMS(), $result->getPaymentUrl());
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $api = DohonePayOut::mobile()
            ->setAmount($transaction->getConvertedAmount(asInt: true))
            ->setMethod(request()->get('mode'))
            ->setPayerPhoneAccount(config("dohone.payOutPhoneAccount"))
            ->setReceiverAccount(request()->get('receiver_phone'))
            ->setReceiverCity(request()->get('receiver_city'))
            ->setReceiverCountry(request()->get('receiver_country'))
            ->setReceiverName(request()->get('receiver_name'));
        $result = $api->post();

        $this->successful = $result->isSuccess();
        $this->setTransaction($transaction);
        $this->message = $result->getMessage();
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $result->getErrors(), $result->shouldVerifySMS(), $result->getPaymentUrl());
        if ($this->successful()) {
            $this->message = "Well Done";
            $this->setExternalId($result->getMessage());
        }
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        Log::debug("**Payment** | Dohone : callback " . $request->rI, $request->all());

        $this->findTransaction($request, 'rI');

        $data = $request->all();
        try {
            $convertedAmount = $this->transaction->getConvertedAmount(asInt: true);
            $this->successful = $data["hash"] === md5($data["idReqDoh"] . $data["rI"] . $data["rMt"] . config("dohone.payOutHashCode")) && ((int)$data["rMt"] >= $convertedAmount);
            if ($this->successful()) {
                $this->message = "Well Done";
                $this->setExternalId($data["idReqDoh"]);
            } else {
                Log::error("**Payment** | Dohone : Tx {$this->transaction->id} invalid hashcode or amount mismatch  $convertedAmount <> {$data["rMt"]} ");
            }
            $this->response = new FinanceProviderGatewayResponse($this->transaction, null, $request->all());
        } catch (Exception $exception) {
            $this->message = $exception->getMessage();
        }

        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        // TODO: Implement onWithdrawalSuccess() method.
        return $this;
    }

    public function SMSConfirmation($code, $phone): PaymentProviderGateway
    {
        $result = DohonePayIn::sms()
            ->setCode($code)
            ->setPhone($phone)
            ->get();
        $this->successful = $result->isSuccess();
        $this->message = $result->getMessage();
        $this->response = new FinanceProviderGatewayResponse(null, null, $result->getErrors(), $result->shouldVerifySMS(), $result->getPaymentUrl());
        return $this;
    }

    public function channel(): string
    {
        return Arr::get($this->transaction->end_log,"parameters.mode","");
    }
}