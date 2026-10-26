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
`nycorp/lite-api` is no longer required. When lite-api 1.x is installed, the finance responses (`DefResponse`) and
exceptions (`FinanceException`) extend the lite-api ones, so code typed against lite-api keeps working; set
`FINANCE_LITE_API_BRIDGE=0` to turn that off.

#### Configuration

Publish migration file and config

```shell
php artisan vendor:publish --provider="NYCorp\Finance\FinanceServiceProvider"
```

Run migration

```shell
php artisan migrate
```

Get exchange rate API KEY of [https://api.exchangeratesapi.io](https://api.exchangeratesapi.io) and set in .env
```env
EXCHANGE_RATE_API_KEY=<api key>
# optional, defaults to https://api.exchangeratesapi.io/v1/latest
EXCHANGE_RATE_API_URL=
```

Set a dedicated signing key (see [Integrity](#integrity))
```env
FINANCE_SIGNATURE_KEY_ID=k1
FINANCE_SIGNATURE_KEY=base64:...   # php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
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

Zero amounts are refused, unless explicitly allowed (e.g. to record a plan granted for free)

```php
return User::first()->credit(DefaultPaymentProvider::getId(), 0, 'Pro plan granted', allowZeroAmount: true)
```

Public, translatable description and search tags (optional on credit, debit, deposit, withdrawal and transfer)

```php
use NYCorp\Finance\Models\TransactionDisplay;

$user->credit(DefaultPaymentProvider::getId(), 9.99, 'plan Pro #42 (internal)',
    display: TransactionDisplay::make('subscription.payment')
        ->with('plan', 'Pro')                         // string, int, float, bool, null or backed enum
        ->withDate('from', $subscription->starts_at), // Y-m-d by default
    tags: ['plan:pro', 'invoice:FAC-001']);

$transaction->display;           // TransactionDisplay (key(), params(), param(), translate())
$transaction->describe();        // __('subscription.payment', [...]) from your lang files, else the description
$transaction->tag('campaign:CMP-12');   // tags can be added later, even on a finalized transaction
FinanceTransaction::tagged('plan:pro', 'invoice:FAC-001')->get();   // all the tags
FinanceTransaction::taggedAny('merchant:eneo', 'merchant:camwater')->get();   // any of them
```

`TransactionDisplay` is immutable and validated when built (an invalid key or parameter fails in your code, not in
the library). It stores the key and its parameters, never the text, and is part of the v3 signature; tags live in an indexed
table and are not signed (they are for search, not proof).

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
        # For example here the transaction id is inside the cpm_trans_id in your case it maybe another value
        if ($this->findTransaction($request, 'cpm_trans_id') === null) {
            return $this; // answered "not found", nothing changes
        }

        if (!hash_equals($this->expectedSignature($request), (string)$request->get('signature'))) {
            $this->rejectCallback('invalid signature'); // the transaction stays PENDING
            return $this;
        }

        match ($request->get('status')) {
            'ACCEPTED' => $this->successful = true,
            'REFUSED' => $this->markDefinitiveFailure(), // closes the transaction as FAILED
            default => null,                             // still pending: nothing changes
        };
        if ($this->successful()) {
            $this->setExternalId($request->get('operator_id')); // COMPLETED needs success AND a reference
        }

        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        return $this;
    }
}
```

The notification controller only applies the provider's verdict (`resolveState()`): a success with an external id
completes the transaction, `markDefinitiveFailure()` fails it, anything else leaves it PENDING, and a callback rejected,
for an unknown transaction or for another provider's transaction changes nothing. Repeated callbacks are idempotent.
Do not log `$request->all()`: callbacks carry personal data.

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


## Integrity

Every money movement is tamper-evident:

* **Signature v3**: transactions (start signature + checksum), wallets and ledgers are signed with HMAC-SHA256 and
  the key `FINANCE_SIGNATURE_KEY`, stored as `v3:{keyId}:{hmac}`. Without the key, nobody with database access can
  recompute them. Rows signed by older versions (v1, v2) are still verified. Without a key the library keeps the
  unkeyed v2 hashes.
* **Key rotation**: give the new key a new `FINANCE_SIGNATURE_KEY_ID` and keep the old one in
  `FINANCE_SIGNATURE_PREVIOUS_KEYS=k1:base64:...`.
* **Hash chain** (`finance_chain_entries`): each finalized transaction is appended to its owner's chain, each entry
  signing the previous one. A deleted, inserted or modified entry breaks the chain; sealed transactions cannot be deleted.
* **Audit log** (`finance_audit_logs`): signed, append-only rows for finalizations, rejected callbacks, balance
  recalculations and threshold changes, with the actor and the context (route or command).
* Finalization (state, checksum, lock, chain entry, audit row, balance increment) is one database transaction; a debit
  locks the account and reserves the pending debits of the last `FINANCE_PENDING_RESERVATION_TTL` minutes.

Commands:

```shell
php artisan finance:verify-integrity            # exit code 1 on any error; --fix-balances, --json
php artisan finance:seal-history                # once after upgrading: seal the transactions finalized before the chain
php artisan finance:expire-pending --minutes=10080   # cancel transactions a provider never answered
```

Upgrading to 2.0: publish and run the four new migrations, set the key, run `finance:seal-history`, restart the queue
workers, then schedule `finance:verify-integrity`.

## Tests

```shell
composer install
vendor/bin/phpunit                    # SQLite in memory
FINANCE_TEST_DB=mysql FINANCE_TEST_DB_DATABASE=<throw-away db> FINANCE_TEST_DB_PASSWORD=... vendor/bin/phpunit
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