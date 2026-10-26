<?php

namespace NYCorp\Finance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use NYCorp\Finance\Traits\FinanceAccountTrait;

class User extends Model
{
    use FinanceAccountTrait;

    protected $table = 'users';
    protected $guarded = [];
}
