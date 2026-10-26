<?php


namespace NYCorp\Finance\Http\Payment;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Interfaces\InternalProvider;
use NYCorp\Finance\Interfaces\IPaymentProvider;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Models\ResponseCode;

abstract class PaymentProviderGateway implements IPaymentProvider
{
    protected bool $successful = false;
    protected string $message = "Oops something when wrong";
    protected FinanceProviderGatewayResponse $response;
    protected FinanceTransaction $transaction;
    protected bool $isWithdrawalRealTime = false;
    protected bool $definitiveFailure = false;
    protected ?string $rejectionReason = null;
    private FinanceProvider $financeProvider;

    public function __construct()
    {
        $this->transaction = new FinanceTransaction();
    }

    /**
     * @throws LiteResponseException
     */
    public static function load($id = null): PaymentProviderGateway
    {
        if ($id !== null && $id !== '') {
            foreach (self::register() as [$gatewayProvider, $registeredProvider]) {
                //Id can be either provider id, or assigned_id (int and numeric string are the same id)
                if ((string)$id === (string)$registeredProvider->{FinanceProvider::ASSIGNED_ID} || (string)$id === (string)$registeredProvider->getKey()) {
                    if ($gatewayProvider instanceof InternalProvider) {
                        // Bypass restrictions for the internal provider
                        $registeredProvider->is_deposit_available = true;
                        $registeredProvider->is_withdrawal_available = true;
                        $registeredProvider->is_available = true;
                    }

                    $gatewayProvider->financeProvider = $registeredProvider;
                    return $gatewayProvider;
                }
            }
        }

        throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, message: 'Gateway Not Found');
    }

    /**
     * Register the configured providers: one query for the known ones, an insert only for the missing ones.
     *
     * @return array<string, array{0: PaymentProviderGateway, 1: FinanceProvider}> indexed by assigned id
     */
    public static function register(): array
    {
        $gateways = [];
        foreach (ConfigReader::getPaymentProviders() as $clazz) {
            try {
                $gatewayProvider = new $clazz();
                if ($gatewayProvider instanceof self) {
                    $gateways[(string)$gatewayProvider::getId()] = $gatewayProvider;
                }
            } catch (\Exception|\Throwable $exception) {
                Log::error('Loading Payment Provider Gateway with ' . $exception->getMessage());
            }
        }

        $registered = FinanceProvider::whereIn(FinanceProvider::ASSIGNED_ID, array_keys($gateways))->get()->keyBy(FinanceProvider::ASSIGNED_ID);

        $providers = [];
        foreach ($gateways as $assignedId => $gatewayProvider) {
            try {
                $providers[$assignedId] = [$gatewayProvider, $registered->get($assignedId) ?? FinanceProvider::firstOrCreate(
                    [FinanceProvider::ASSIGNED_ID => $assignedId],
                    [
                        FinanceProvider::NAME => $gatewayProvider::getName(),
                        FinanceProvider::IS_AVAILABLE => $gatewayProvider->isAvailable(),
                        FinanceProvider::IS_WITHDRAWAL_AVAILABLE => $gatewayProvider->isWithdrawalAvailable(),
                        FinanceProvider::IS_DEPOSIT_AVAILABLE => $gatewayProvider->isDepositAvailable(),
                        FinanceProvider::IS_PUBLIC => $gatewayProvider->isPublic(),
                    ]
                )];
            } catch (\Exception|\Throwable $exception) {
                Log::error('Registering Payment Provider ' . $assignedId . ' with ' . $exception->getMessage());
            }
        }

        return $providers;
    }

    abstract public static function getId(): string;

    protected function isAvailable(): bool
    {
        return true;
    }

    protected function isWithdrawalAvailable(): bool
    {
        return false;
    }

    protected function isDepositAvailable(): bool
    {
        return true;
    }

    public function isPublic(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    protected function setMessage(string $message): void
    {
        $this->message = $message;
    }

    private static function payerInfo(string $targetAccount,string $m, string $targetName = "", mixed $channel = ''): static
    {
        \request()->request->add([
            "target_account" => $targetAccount,
            "name" => $targetName,
            "channel" => $channel
        ]);
        return new static;
    }

    public function depositNotificationUrl(): string
    {
        return route('finance.wallet.deposit.notification', static::getId());
    }

    public function withdrawalNotificationUrl(): string
    {
        return route('finance.wallet.withdrawal.notification', static::getId());
    }

    /**
     * @return mixed
     */
    public function getTransaction(): FinanceTransaction
    {
        return $this->transaction;
    }

    /**
     * @param FinanceTransaction $transaction
     */
    public function setTransaction(FinanceTransaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    /**
     * @return bool
     */
    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * @return bool
     */
    public function isWithdrawalRealTime(): bool
    {
        return $this->isWithdrawalRealTime;
    }

    /**
     * @return mixed
     */
    public function getResponse(): FinanceProviderGatewayResponse
    {
        if (!isset($this->response)) {
            $this->response = new FinanceProviderGatewayResponse($this->transaction->exists ? $this->transaction : null);
        }
        return $this->response;
    }

    /**
     * @param mixed $response
     */
    protected function setResponse(FinanceProviderGatewayResponse $response): void
    {
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function getFinanceProvider(): FinanceProvider
    {
        return $this->financeProvider;
    }

    public function channel(): string
    {
        return '';
    }

    /**
     * @param bool $successful
     */
    protected function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    /**
     * @param mixed $externalId
     */
    protected function setExternalId(string $externalId): void
    {
        $this->transaction->external_id = $externalId;
    }

    protected function getWallet(FinanceTransaction $transaction): FinanceWallet
    {
        $wallet = FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)->where(FinanceWallet::FINANCE_TRANSACTION_ID, $transaction->id)->first();
        if ($wallet === null) {
            $wallet = new FinanceWallet();
        }
        return $wallet;
    }

    /**
     * Returns null when the transaction is unknown; $this->transaction then stays an unsaved model
     * so that provider code reading it does not crash, and the callback is answered "not found".
     */
    protected function findTransaction(Request $request, string $key): ?FinanceTransaction
    {
        $transactionId = $request->get($key);
        $transaction = is_scalar($transactionId) ? FinanceTransaction::find((string)$transactionId) : null;
        if ($transaction === null) {
            $id = static::getId();
            Log::error("**Payment** | $id : order not found");
            $this->transaction = new FinanceTransaction();
            $this->message = "Order not found !";
            $this->successful = false;
            $this->response = new FinanceProviderGatewayResponse(null, null, []);
            return null;
        }
        $this->transaction = $transaction;
        return $this->transaction;
    }

    /**
     * The provider says the payment definitively failed (authenticated callback): the transaction can be closed as FAILED.
     * Without it, an unsuccessful callback leaves the transaction PENDING, so a later genuine success can still complete it.
     */
    protected function markDefinitiveFailure(): void
    {
        $this->successful = false;
        $this->definitiveFailure = true;
    }

    /**
     * The callback could not be authenticated (bad hash/signature): it must not change the transaction.
     */
    protected function rejectCallback(string $reason): void
    {
        $this->successful = false;
        $this->definitiveFailure = false;
        $this->rejectionReason = $reason;
    }

    public function isCallbackRejected(): bool
    {
        return $this->rejectionReason !== null;
    }

    /**
     * A callback may only settle a transaction started with this provider
     */
    public function ownsTransaction(): bool
    {
        return isset($this->financeProvider)
            && (string)$this->transaction->{FinanceTransaction::FINANCE_PROVIDER_ID} === (string)$this->financeProvider->getKey();
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /**
     * State to give to the transaction after a provider answer: COMPLETED only on success with a provider reference,
     * FAILED only on a definitive failure, null to leave it PENDING.
     */
    public function resolveState(): ?string
    {
        if ($this->isCallbackRejected()) {
            return null;
        }

        if ($this->successful() && !empty($this->transaction->{FinanceTransaction::EXTERNAL_ID})) {
            return FinanceTransaction::STATE_COMPLETED;
        }

        return $this->definitiveFailure ? FinanceTransaction::STATE_FAILED : null;
    }
}