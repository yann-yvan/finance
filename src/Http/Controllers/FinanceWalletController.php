<?php

namespace NYCorp\Finance\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use NYCorp\Finance\Models\FinanceWallet;
use Nycorp\LiteApi\Models\ResponseCode;


class FinanceWalletController extends Controller
{
    private Model $accountable;

    /**
     *
     * @param $transaction
     * @param Model $accountable
     * @return JsonResponse
     * @throws Exception
     */
    public static function persist($transaction, Model $accountable, ?string $fromWalletId = null): JsonResponse
    {
        return (new FinanceWalletController())->store($transaction, $accountable, $fromWalletId);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param $transaction
     * @param Model $model
     * @param string|null $fromWalletId
     * @return JsonResponse
     * @throws Exception
     */
    private function store($transaction, Model $model, ?string $fromWalletId = null): JsonResponse
    {
        $this->accountable = $model;
        try {
            $data = [
                FinanceWallet::OWNER_ID => $model->getKey(),
                FinanceWallet::OWNER_TYPE => $model->getClass(),
                FinanceWallet::FINANCE_TRANSACTION_ID => $transaction["id"],
                FinanceWallet::TRANSFER_FROM_WALLET_ID => $fromWalletId,
            ];
            return $this->save($data);
        } catch (Exception   $exception) {
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, $exception->getTrace(), $exception->getMessage());
        }
    }

    public function getModel(): Model
    {
        return new FinanceWallet;
    }

    public function addRule(): array
    {
        return [
            FinanceWallet::OWNER_ID => ['required', "exists:{$this->accountable->getTable()},id"],
            FinanceWallet::OWNER_TYPE => ['required'],
            FinanceWallet::FINANCE_TRANSACTION_ID => ['required', 'exists:finance_transactions,id'],
            FinanceWallet::TRANSFER_FROM_WALLET_ID => ['nullable', 'exists:finance_wallets,id'],
        ];
    }

    public function updateRule(mixed $modelId): array
    {
        return [];
    }
}
