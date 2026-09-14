<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Pairs an integer `*_minor` column with a currency column so models expose Money objects.
 *
 *   protected function casts(): array
 *   {
 *       return ['amount' => MoneyCast::class.':amount_minor,currency'];
 *   }
 *
 * @implements CastsAttributes<Money, Money>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $minorColumn = 'amount_minor',
        private readonly string $currencyColumn = 'currency',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if (! array_key_exists($this->minorColumn, $attributes) || $attributes[$this->minorColumn] === null) {
            return null;
        }

        return new Money(
            (int) $attributes[$this->minorColumn],
            $attributes[$this->currencyColumn] ?? 'INR',
        );
    }

    /** @return array<string, mixed> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->minorColumn => null];
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            $value = Money::fromMajor($value, $attributes[$this->currencyColumn] ?? 'INR');
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException('Expected a Money instance or a numeric value.');
        }

        return [
            $this->minorColumn => $value->minor,
            $this->currencyColumn => $value->currency,
        ];
    }
}
