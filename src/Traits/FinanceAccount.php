<?php


namespace NYCorp\Finance\Traits;




trait FinanceAccount
{
    protected abstract function getId(): int;

    protected abstract function getName(): string;
}