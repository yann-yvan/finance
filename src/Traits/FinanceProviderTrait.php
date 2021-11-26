<?php


namespace NYCorp\Finance\Traits;


trait FinanceProviderTrait
{
    public abstract function getId(): string;

    public abstract function getName(): string;
}