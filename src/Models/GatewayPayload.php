<?php

namespace NYCorp\Finance\Models;

use Illuminate\Support\Str;

class GatewayPayload
{
    protected ?string $orderId = null;
    protected ?float $amount = null;
    protected ?string $accountNumber = null;
    protected ?string $accountName = null;
    protected ?string $phoneNumber = null;
    protected ?string $accountEmail = null;
    protected ?string $accountCity = null;
    protected ?string $accountCountry = null;
    protected ?string $reference = null;
    protected ?string $paymentMethod = null;
    protected ?string $providerResponse = null; // e.g. "mobile_money", "card"
    protected ?array $metadata = [];

    public static function make(): static
    {
        return new static();
    }

    public function setOrderId(string $orderId): static
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    // ───── Fluent Setters ─────

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;
        return $this;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = $accountName;
        return $this;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function setAccountEmail(string $accountEmail): static
    {
        $this->accountEmail = $accountEmail;
        return $this;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function setProviderResponse(string $providerResponse): static
    {
        $this->providerResponse = $providerResponse;
        return $this;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function load(): static
    {
        return $this->fill(request()->all());
    }

    public function setAccountCity(?string $accountCity): GatewayPayload
    {
        $this->accountCity = $accountCity;
        return $this;
    }

    public function setAccountCountry(?string $accountCountry): GatewayPayload
    {
        $this->accountCountry = $accountCountry;
        return $this;
    }


    /**
     * Mass assign properties from array
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $property = Str::camel($key);
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }

        return $this;
    }

    public function store(): void
    {
        request()->request->add($this->toArray());
    }

    /**
     * Return all fields as associative array
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
            'phone_number' => $this->phoneNumber,
            'account_email' => $this->accountEmail,
            'account_city' => $this->accountCity,
            'account_country' => $this->accountCountry,
            'reference' => $this->reference,
            'payment_method' => $this->paymentMethod,
            'provider_response' => $this->providerResponse,
            'metadata' => $this->metadata,
        ];
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getAccountEmail(): ?string
    {
        return $this->accountEmail;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getProviderResponse(): ?string
    {
        return $this->providerResponse;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getAccountCity(): ?string
    {
        return $this->accountCity;
    }

    public function getAccountCountry(): ?string
    {
        return $this->accountCountry;
    }


}