<?php


namespace NYCorp\Finance\Interfaces;


interface IPaymentProvider
{
    public function getId(): string;

    public function getName(): string;
}