[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

## Features

1. make deposit
2. make withdrawal
3. Set account threshold

#### Installation (with Composer)

```composer
composer require nycorp/finance
```

#### Configuration

```shell
php artisan vendor:publish --provider="NYCorp\Finance\FinanceServiceProvider"
```

## Usage

### Add Finance Account Trait to any model

```php
use FinanceAccountTrait;
```

Set Threshold the min balance for an account

```php
return User::first()->setThreshold(100) 
```

For deposit

```php
return User::first()->deposit($request)
```

For withdrawal

```php
return User::first()->withdrawal($request)
```

Check if user can make transaction if his finance account is not disabled

```php
return Company::first()->canMakeTransaction() ? Company::first()->withdrawal($request) : 'Error';
```

Response handle

```php
$response = \Nycorp\LiteApi\Response\DefResponse::parse(User::first()->withdrawal($request));
$response->getBody(); // get the body of the response
$response->isSuccess(); // get the success state as boolean
$response->getMessage(); // get response message
```

[ico-version]: https://img.shields.io/packagist/v/nycorp/finance.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/nycorp/finance.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/nycorp/finance
[link-downloads]: https://packagist.org/packages/nycorp/finance