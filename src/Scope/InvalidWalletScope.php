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
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Model   $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->whereHas('transaction', function ($query) use ($model) {
            $query->where('verify_at', '!=', null)
                ->where('external_id', '!=', null)
                ->where('end_signature', '!=', null)
                ->where('state', FinanceTransaction::STATE_SUCCESS);
        });
    }
}
