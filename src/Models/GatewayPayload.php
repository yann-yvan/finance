<?php

namespace NYCorp\Finance\Models;

use Illuminate\Support\Str;

class GatewayPayload
{
    private const PREFIX = "#___";
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
    protected ?string $callbackUrl = null;

    public static function make(): static
    {
        return new static();
    }

    public function load(): static
    {
        return $this->fill(request()->request->all());
    }

    /**
     * Mass assign properties from array
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $property = Str::replaceFirst(self::PREFIX,'',Str::camel($key));
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
     * Automatically convert all class properties to associative array
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        // Get only non-static properties of the current class
        $properties = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = Str::snake($property->getName());
            $key = self::PREFIX . $name;
            $data[$key] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Return all fields as associative array
     */
    public function toArrays(): array
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

    public function setOrderId(string $orderId): static
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;
        return $this;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = $accountName;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getAccountEmail(): ?string
    {
        return $this->accountEmail;
    }

    public function setAccountEmail(string $accountEmail): static
    {
        $this->accountEmail = $accountEmail;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getProviderResponse(): ?string
    {
        return $this->providerResponse;
    }

    public function setProviderResponse(string $providerResponse): static
    {
        $this->providerResponse = $providerResponse;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getAccountCity(): ?string
    {
        return $this->accountCity;
    }

    public function setAccountCity(?string $accountCity): GatewayPayload
    {
        $this->accountCity = $accountCity;
        return $this;
    }

    public function getAccountCountry(): ?string
    {
        return $this->accountCountry;
    }

    public function setAccountCountry(?string $accountCountry): GatewayPayload
    {
        $this->accountCountry = $accountCountry;
        return $this;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    public function setCallbackUrl(?string $callbackUrl): GatewayPayload
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }


}