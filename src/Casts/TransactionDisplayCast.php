<?php

namespace NYCorp\Finance\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use NYCorp\Finance\Models\TransactionDisplay;

/**
 * Stores a TransactionDisplay as JSON and reads it back as a TransactionDisplay.
 */
class TransactionDisplayCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TransactionDisplay
    {
        if ($value === null || $value === '') {
            return null;
        }
        return TransactionDisplay::fromArray(is_array($value) ? $value : json_decode((string)$value, true));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof TransactionDisplay) {
            throw new InvalidArgumentException('The display must be a ' . TransactionDisplay::class);
        }
        return json_encode($value->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
