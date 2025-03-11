<?php

namespace NYCorp\Finance\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use NYCorp\Finance\Models\FinanceTransaction;

class FinanceTransactionSuccessEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public Model $model;
    public FinanceTransaction $financeTransaction;

    /**
     * @param Model $model
     * @param FinanceTransaction $financeTransaction
     */
    public function __construct(Model $model, FinanceTransaction $financeTransaction)
    {
        $this->model = $model;
        $this->financeTransaction = $financeTransaction;
    }


}