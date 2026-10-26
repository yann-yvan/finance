<?php


namespace NYCorp\Finance\Http\Payment;


use Dohone\PayIn\DohonePayIn;
use Dohone\PayOut\DohonePayOut;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\GatewayPayload;

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
        $payload = GatewayPayload::make()->load();
        $api = DohonePayIn::payWithAPI()
            ->setAmount($transaction->getConvertedAmount(asInt: true))
            ->setClientPhone($payload->getPhoneNumber() ?? request()->get('phone'))
            ->setClientName($payload->getAccountName())
            ->setClientEmail($payload->getAccountEmail())
            ->setCommandID($transaction->id)
            ->setNotifyPage($this->depositNotificationUrl())
            ->setOTPCode(request()->get('otp'))
            ->setDescription($transaction->description)
            ->setMethod($payload->getPaymentMethod() ?? request()->get('mode'));

        $result = $api->get();
        $this->successful = $result->isSuccess();
        $this->message = $result->getMessage();
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $result->getErrors(), $result->shouldVerifySMS(), $result->getPaymentUrl());
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $payload = GatewayPayload::make()->load();
        $api = DohonePayOut::mobile()
            ->setAmount($transaction->getConvertedAmount(asInt: true))
            ->setMethod($payload->getPaymentMethod())
            ->setReceiverAccount($payload->getPhoneNumber())
            ->setReceiverCity($payload->getAccountCity())
            ->setTransactionID($transaction->id)
            ->setReceiverCountry($payload->getAccountCountry())
            ->setReceiverName($payload->getAccountName());

        $result = $api->post();

        $this->successful = $result->isSuccess();
        $this->setTransaction($transaction);
        $this->message = $result->getMessage();
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $result->getErrors(), $result->shouldVerifySMS(), $result->getPaymentUrl());
        if ($this->successful()) {
            $this->message = "Well Done";
            $ref = trim(Arr::last(explode("/", $result->getMessage())));
            $this->setExternalId($ref);
            \request()->request->add([
                "response" => $result->getMessage(),
                "mode" => $api->getMethod()
            ]);
            FinanceTransactionController::close($transaction, FinanceTransaction::STATE_COMPLETED);
        }
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        Log::debug("**Payment** | Dohone : deposit callback received");

        if ($this->findTransaction($request, 'rI') === null) {
            return $this;
        }

        $data = $request->all();
        try {
            $expected = md5(Arr::get($data, 'idReqDoh', '') . Arr::get($data, 'rI', '') . Arr::get($data, 'rMt', '') . config("dohone.payOutHashCode"));
            $received = Arr::get($data, 'hash');
            if (!is_string($received) || !hash_equals($expected, strtolower($received))) {
                $this->rejectCallback('invalid hash');
                $this->response = new FinanceProviderGatewayResponse($this->transaction, null, []);
                return $this;
            }

            $convertedAmount = $this->transaction->getConvertedAmount(asInt: true);
            if ((int)Arr::get($data, 'rMt') >= $convertedAmount) {
                $this->successful = true;
                $this->message = "Well Done";
                $this->setExternalId((string)$data["idReqDoh"]);
            } else {
                // Authenticated by the hash: the amount paid is definitively short
                Log::error("**Payment** | Dohone : Tx {$this->transaction->id} amount mismatch $convertedAmount <> " . Arr::get($data, 'rMt'));
                $this->markDefinitiveFailure();
                $this->message = "Amount mismatch";
            }
            $this->response = new FinanceProviderGatewayResponse($this->transaction, null, []);
        } catch (Exception $exception) {
            $this->message = $exception->getMessage();
        }

        return $this;
    }

    /**
     * Dohone payouts are settled synchronously in withdrawal(): a payout notification never changes a transaction.
     */
    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        Log::debug("**Payment** | Dohone : withdrawal callback received");

        if ($this->findTransaction($request, 'rI') ?? $this->findTransaction($request, 'transaction_id')) {
            $this->message = "Dohone payouts are settled synchronously";
            $this->response = new FinanceProviderGatewayResponse($this->transaction, null, []);
        }

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
        return Arr::get($this->transaction->end_log, "parameters.mode", "");
    }
}