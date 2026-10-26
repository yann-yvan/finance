<?php

namespace NYCorp\Finance\Models;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Public, translatable description of a transaction: a translation key and its named parameters.
 * The host application translates it with its own lang files; the final text is never stored.
 *
 * TransactionDisplay::make('subscription.payment')
 *     ->with('plan', 'Pro')
 *     ->withDate('from', $subscription->starts_at);
 *
 * Invalid data fails here, in the calling code, before reaching the library.
 */
final class TransactionDisplay implements JsonSerializable
{
    public const MAX_KEY_LENGTH = 191;

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    private function __construct(private readonly string $key, private readonly array $params = [])
    {
    }

    /**
     * @param array<string, string|int|float|bool|BackedEnum|DateTimeInterface|null> $params
     * @throws InvalidArgumentException on an empty or too long key, or a parameter that is not a named scalar
     */
    public static function make(string $key, array $params = []): self
    {
        $key = trim($key);
        if ($key === '' || mb_strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException('The display key must be a non-empty string of at most ' . self::MAX_KEY_LENGTH . ' characters');
        }

        $display = new self($key);
        foreach ($params as $name => $value) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('The display params must be named');
            }
            $display = $display->with($name, $value);
        }
        return $display;
    }

    /**
     * Rebuild from the stored form (['key' => ..., 'params' => [...]]); null when empty.
     */
    public static function fromArray(?array $data): ?self
    {
        if (empty($data['key'])) {
            return null;
        }
        return self::make((string)$data['key'], (array)($data['params'] ?? []));
    }

    /**
     * Returns a copy with the parameter set. An enum gives its value, a date its ISO 8601 form (see withDate()).
     */
    public function with(string $name, string|int|float|bool|BackedEnum|DateTimeInterface|null $value): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A display param needs a name');
        }

        $value = match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            default => $value,
        };

        return new self($this->key, array_merge($this->params, [$name => $value]));
    }

    public function withDate(string $name, DateTimeInterface $date, string $format = 'Y-m-d'): self
    {
        return $this->with($name, $date->format($format));
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Text in the host's lang files, or null when the key has no translation
     */
    public function translate(?string $locale = null): ?string
    {
        $text = trans($this->key, $this->params, $locale);
        return is_string($text) && $text !== $this->key ? $text : null;
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'params' => $this->params];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $other->toArray() === $this->toArray();
    }
}
