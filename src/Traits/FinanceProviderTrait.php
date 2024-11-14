<?php


namespace NYCorp\Finance\Traits;


trait FinanceProviderTrait
{
    abstract public static function getId(): string;

    abstract public function getName(): string;
}