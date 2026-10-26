<?php

namespace NYCorp\Finance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NYCorp\Finance\Traits\FinanceLedgerTrait;

class Partner extends Model
{
    use FinanceLedgerTrait;

    protected $table = 'partners';
    protected $guarded = [];
}
