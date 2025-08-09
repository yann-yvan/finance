<?php


namespace NYCorp\Finance\Http\Payment;


use Dohone\PayIn\DohonePayIn;
use Dohone\PayOut\DohonePayOut;
use Exception;
use Illuminate\Http\Request;
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

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $api = DohonePayIn::payWithAPI()
            ->setAmount($transaction->getConvertedAmount())
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
            ->setAmount($transaction->getConvertedAmount())
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
        $this->transaction = FinanceTransaction::find($request->rI);
        if (empty($this->transaction)) {
            $this->message = "Order not found !";
            $this->successful = false;
            $this->response = new FinanceProviderGatewayResponse(null, null, $request->all());
            return $this;
        }

        $data = $request->all();
        try {
            $this->successful = $request->hash === md5($data["idReqDoh"] . $data["rI"] . $data["rMt"] . config("dohone.payOutHashCode"));
            if ($this->successful()) {
                $this->message = "Well Done";
                $this->setExternalId($data["idReqDoh"]);
            }
            $this->response = new FinanceProviderGatewayResponse($data["rI"], null, $request->all());
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

    public static function getCurrency(): string
    {
        return 'XAF';
    }
}