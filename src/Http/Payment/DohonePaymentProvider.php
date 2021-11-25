<?php


namespace NYCorp\Finance\Http\Payment;


use Dohone\PayIn\DohonePayIn;
use Dohone\PayOut\DohonePayOut;
use Exception;
use Illuminate\Http\Request;
use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Traits\FinanceProviderTrait;
use NYCorp\Finance\Traits\PaymentProviderTrait;

class DohonePaymentProvider extends PaymentProviderGateway
{
    use PaymentProviderTrait;
    use FinanceProviderTrait;

    protected $isWithdrawalRealTime = true;

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
            ->setClientEmail($transaction->wallet->owner->{config(FinanceServiceProvider::FINANCE_CONFIG_NAME . ".user_email_field")})
            //->setClientName("$user->first_name $user->last_name")
            ->setCommandID($transaction->id)
            ->setNotifyPage(route('finance.wallet.deposit.success.dohone'))
            ->setOTPCode(request()->get('otp'))
            ->setDescription($transaction->description)
            ->setMethod(request()->get('mode'));
        //$api->setClientID(auth()->id());
        $result = $api->get();
        $this->successful = $result->isSuccess();
        $this->message = $result->getMessage();
        $this->response = ["errors" => $result->getErrors(), "sms_verification_required" => $result->shouldVerifySMS(), "payment_url" => $result->getPaymentUrl()];
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $api = DohonePayOut::mobile()
            ->setAmount($transaction->amount)
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
        $this->response = ["errors" => $result->getErrors(), "sms_verification_required" => $result->shouldVerifySMS(), "payment_url" => $result->getPaymentUrl()];
        if ($this->successful()) {
            $this->message = "Well Done";
            $this->setExternalId($result->getMessage());
        }
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        $this->transaction = FinanceTransaction::find($request->ref);
        if (empty($this->transaction)) {
            $this->message = "Order not found !";
            $this->successful = false;
            $this->response = $request->all();
            return $this;
        }

        $data = $request->all();
        try {
            $this->successful = $request->hash == md5($data["idReqDoh"] . $data["rI"] . $data["rMt"] . config("dohone.payOutHashCode"));
            if ($this->successful()) {
                $this->message = "Well Done";
                $this->setExternalId($data["idReqDoh"]);
            }
        } catch (Exception $exception) {
            $this->message = $exception->getMessage();
        }
        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        // TODO: Implement onWithdrawalSuccess() method.
    }

    public function SMSConfirmation($code, $phone): PaymentProviderGateway
    {
        $result = DohonePayIn::sms()
            ->setCode($code)
            ->setPhone($phone)
            ->get();
        $this->successful = $result->isSuccess();
        $this->message = $result->getMessage();
        $this->response = ["errors" => $result->getErrors(), "sms_verification_required" => $result->shouldVerifySMS(), "payment_url" => $result->getPaymentUrl()];
        return $this;
    }
}