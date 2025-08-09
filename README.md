[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

## Features

1. make deposit
2. make withdrawal
3. Set account threshold
4. Listen to success transaction
5. Custom payment provider

#### Installation (with Composer)

```shell
composer require nycorp/finance
```
Then
```shell
composer require nycorp/lite-api
```

#### Configuration

Publish migration file and config

```shell
php artisan vendor:publish --provider="NYCorp\Finance\FinanceServiceProvider"
```

Run migration

```shell
php artisan migrate
```

## Usage

### Add Finance Account Trait to any model

```php
use FinanceAccountTrait;
```

(Optional) Set Threshold the min balance for an account

```php
return User::first()->setThreshold(100) 
```

For deposit

```php
return User::first()->deposit(DefaultPaymentProvider::getId(), 12, $description)
```

Get balance

```php
return User::first()->balance
```

For withdrawal

```php
return User::first()->withdrawal(DefaultPaymentProvider::getId(), 12, $description)
```

Customize the canWithdraw in the model (Optional)

```php
public function canWithdraw(float $amount, bool $forceBalanceCalculation): bool
    {
        //EX : Set to true because the account is debited only when the service is consumed
        return true;
    }
```

Set model currency by adding this method in the model

```php
public function getCurrency()
    {
        // Implement your logic to get currency here the default value is set in the finance config file
        return \NYCorp\Finance\Http\Core\ConfigReader::getDefaultCurrency();
    }
```

Check if user can make transaction if his finance account is not disabled

```php
return Company::first()->canMakeTransaction() ? Company::first()->withdrawal(DefaultPaymentProvider::getId(), 12, $description) : 'Your account is disabled';
```

Check if user can make transaction if his finance account has enough balance base on threshold use true to force balance
calculation

```php
return Company::first()->canWithdraw(100,true) ? Company::first()->withdrawal(DefaultPaymentProvider::getId(), 12, $description) : 'Insufficient balance';
```

## To listen to success transaction

```shell
php artisan make:listener SuccessFinanceTransactionListener --event=FinanceTransactionSuccessEvent
```
```php
 /**
     * Handle the event.
     */
    public function handle(FinanceTransactionSuccessEvent $event): void
    {
        # In case you handle multiple model
        match (get_class($event->model)) {
            Model1::class => $this->handleModel1($event),
            Model2::class => $this->hanleModel2($event),
            default => static fn() => Log::warning("FinanceTransactionSuccessEvent Model not handle")
        };
    }
```

## Custom Provider

```php
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;

class CustomPaymentProvider extends PaymentProviderGateway
{

    public static function getName(): string
    {
        return 'CustomProvider';
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        #Your custom logic here
        
        //use this url for callback
        $callbackUrl = self::depositNotificationUrl();
        
        $amountToPay = $transaction->getConvertedAmount();

        $response = Http::post('https://api-checkout/v2/payment', $formData);
        $this->successful = $response->successful();
        $this->message = $response->json('description');
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $response->body(), false, $response->json('data.payment_url'));
        return $this;
    }

    public static function getId(): string
    {
        return 'MY_PROVIDER_ID';
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        #Your custom logic here
        
        //use this url for callback
        $callbackUrl = self::withdrawalNotificationUrl();

        $response = Http::post('https://api-checkout/v2/payment', $formData);
        $this->successful = $response->successful();
        $this->message = $response->json('description');
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id, $response->body(), false, $response->json('data.payment_url'));
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        Log::debug("**Payment** | " . self::getId() . ": callback " . $request->cpm_trans_id, $request->all());
        
        # For example here the transaction id is inside the cpm_trans_id in your case it maybe another value
        if ($this->findTransaction($request, 'cpm_trans_id') === null) {
            return $this;
        }

        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        return $this;
    }

    protected function findTransaction(Request $request, string $key): ?FinanceTransaction
    {
        $transactionId = Arr::get($request->all(), $key);
        $this->transaction = FinanceTransaction::find($transactionId);
        if (empty($this->transaction)) {
            $id = self::getId();
            Log::error("**Payment** | $id : order not found $transactionId");
            $this->message = "Order not found !";
            $this->successful = false;
            $this->response = new FinanceProviderGatewayResponse(null, null, $request->all());
        }
        return $this->transaction;
    }
}
```

## Register provider in config

```php
return [
    'default_payment_provider_id' => 'LOCAL_PROVIDER',
    'default_payment_provider_name' => env('APP_NAME')."'s Local Provider",
    'default_threshold' => 0, #minimum account balance applied to all model
    'default_currency' => 'USD',
    'refresh_account_ttl' => 60, #in minute
    'payment_providers' => [
        \NYCorp\Finance\Http\Payment\DefaultPaymentProvider::class,
        \NYCorp\Finance\Http\Payment\CustomPaymentProvider::class,
    ],

    'force_balance_check_min_amount' => 5000,
    'prefix' => 'finance',
    'middleware' => ['api'],


    'user_email_field' => "email",
    'finance_account_id_parameter' => "finance_account_id",
];
```


## Response handle

```php
$response = \Nycorp\LiteApi\Response\DefResponse::parse(User::first()->withdrawal(DefaultPaymentProvider::getId(), 12, $description));
$response->getBody(); // get the body of the response
$response->isSuccess(); // get the success state as boolean
$response->getMessage(); // get response message
```

[ico-version]: https://img.shields.io/packagist/v/nycorp/finance.svg?style=flat-square

[ico-downloads]: https://img.shields.io/packagist/dt/nycorp/finance.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/nycorp/finance

[link-downloads]: https://packagist.org/packages/nycorp/finance