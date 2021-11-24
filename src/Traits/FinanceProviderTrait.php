<?php


namespace NYCorp\Finance\Traits;


trait FinanceProviderTrait
{
    public abstract function getId(): int;

    public abstract function getName(): string;
}