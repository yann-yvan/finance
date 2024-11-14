<?php


namespace NYCorp\Finance\Models;


class FinanceProviderGatewayResponse
{
    protected $transaction;
    protected $wallet;
    protected $error;
    protected $smsVerificationRequired;
    protected $paymentUrl;

    /**
     * FinanceProviderGatewayResponse constructor.
     *
     * @param $transaction
     * @param $wallet
     * @param $error
     * @param bool $smsVerificationRequired
     * @param $paymentUrl
     */
    public function __construct($transaction = null, $wallet = null, $error = null, bool $smsVerificationRequired = false, $paymentUrl = null)
    {
        $this->transaction = $transaction;
        $this->wallet = $wallet;
        $this->error = $error;
        $this->smsVerificationRequired = $smsVerificationRequired;
        $this->paymentUrl = $paymentUrl;
    }

    public static function fromArray($array)
    {

    }


    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param mixed $transaction
     */
    public function setTransaction($transaction): void
    {
        $this->transaction = $transaction;
    }

    /**
     * @return mixed
     */
    public function getWallet()
    {
        return $this->wallet;
    }

    /**
     * @param mixed $wallet
     */
    public function setWallet($wallet): void
    {
        $this->wallet = $wallet;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getSmsVerificationRequired()
    {
        return $this->smsVerificationRequired;
    }

    /**
     * @param mixed $smsVerificationRequired
     */
    public function setSmsVerificationRequired($smsVerificationRequired): void
    {
        $this->smsVerificationRequired = $smsVerificationRequired;
    }

    /**
     * @return mixed
     */
    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }

    /**
     * @param mixed $paymentUrl
     */
    public function setPaymentUrl($paymentUrl): void
    {
        $this->paymentUrl = $paymentUrl;
    }


    public function toArray(): array
    {
        return [
            "transaction" => $this->transaction,
            "wallet" => $this->wallet,
            "errors" => $this->error,
            "smsVerificationRequired" => $this->smsVerificationRequired,
            "paymentUrl" => $this->paymentUrl,
        ];
    }
}