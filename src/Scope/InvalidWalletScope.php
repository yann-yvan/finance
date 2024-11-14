<?php


namespace NYCorp\Finance\Scope;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use NYCorp\Finance\Models\FinanceTransaction;

class InvalidWalletScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('transaction', function ($query) use ($model) {
            $query->where(FinanceTransaction::VERIFY_AT, '!=', null)
                ->where(FinanceTransaction::EXTERNAL_ID, '!=', null)
                ->where(FinanceTransaction::CHECKSUM, '!=', null)
                ->where(FinanceTransaction::IS_LOCKED, true)
                ->where(FinanceTransaction::STATE, FinanceTransaction::STATE_COMPLETED);
        });
    }
}
