<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;
use Stringable;

/**
 * An amount of money, held as an integer number of minor units (paise, cents).
 *
 * Floats are never used for money anywhere in this application. 0.1 + 0.2 is not
 * 0.3 in binary floating point, and a rupee that goes missing in a group of six
 * people is the bug users notice first.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /** Currencies whose minor unit is not 1/100 of the major unit. */
    private const EXPONENTS = [
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0, 'ISK' => 0,
        'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'JOD' => 3, 'TND' => 3,
    ];

    public function __construct(
        public int $minor,
        public string $currency = 'INR',
    ) {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Currency must be a 3-letter code, got [{$currency}].");
        }
    }

    public static function zero(string $currency = 'INR'): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * Build from a human-entered value such as "1,249.50" or 1249.5.
     * Parsed as a decimal string so the fractional part is never rounded twice.
     */
    public static function fromMajor(int|float|string $amount, string $currency = 'INR'): self
    {
        $currency = strtoupper($currency);
        $exponent = self::exponentFor($currency);

        $clean = is_string($amount)
            ? preg_replace('/[^0-9.\-]/', '', $amount)
            : (string) $amount;

        if ($clean === '' || $clean === '-' || $clean === null) {
            return self::zero($currency);
        }

        $negative = str_starts_with($clean, '-');
        $clean = ltrim($clean, '-');

        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '');
        $fraction = substr(str_pad($fraction, $exponent + 1, '0'), 0, $exponent + 1);

        $minor = (int) $whole * (10 ** $exponent);
        if ($exponent > 0) {
            $kept = (int) substr($fraction, 0, $exponent);
            $next = (int) substr($fraction, $exponent, 1);
            $minor += $kept + ($next >= 5 ? 1 : 0);
        } elseif ((int) substr($fraction, 0, 1) >= 5) {
            $minor += 1;
        }

        return new self($negative ? -$minor : $minor, $currency);
    }

    public static function exponentFor(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function negated(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function absolute(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    /** Multiply by a rate, rounding half away from zero. */
    public function times(float|int $factor): self
    {
        $product = $this->minor * $factor;

        return new self((int) round($product, 0, PHP_ROUND_HALF_UP), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    /**
     * Split this amount across weighted buckets so the parts always sum back to the whole.
     *
     * Uses the largest-remainder method: everyone gets the floor of their share, then the
     * leftover minor units go one at a time to whoever was rounded down hardest. Ties break
     * on key order, so the same input always produces the same output — important, because
     * a split that shifts by a paisa on every page load looks broken.
     *
     * @param  array<array-key, float|int>  $weights
     * @return array<array-key, self>
     */
    public function allocate(array $weights): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('Cannot allocate money across zero buckets.');
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new InvalidArgumentException('Allocation weights must sum to a positive number.');
        }

        $sign = $this->minor < 0 ? -1 : 1;
        $pool = abs($this->minor);

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $exact = $pool * $weight / $totalWeight;
            $floor = (int) floor($exact);
            $shares[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $allocated += $floor;
        }

        $leftover = $pool - $allocated;

        $order = array_keys($remainders);
        usort($order, function ($a, $b) use ($remainders, $weights) {
            return [$remainders[$b], $weights[$b]] <=> [$remainders[$a], $weights[$a]];
        });

        foreach ($order as $key) {
            if ($leftover <= 0) {
                break;
            }
            $shares[$key]++;
            $leftover--;
        }

        return array_map(fn (int $minor): self => new self($sign * $minor, $this->currency), $shares);
    }

    /** @param iterable<self> $items */
    public static function sum(iterable $items, string $currency = 'INR'): self
    {
        $total = self::zero($currency);

        foreach ($items as $item) {
            $total = $total->plus($item);
        }

        return $total;
    }

    public function toMajor(): string
    {
        $exponent = self::exponentFor($this->currency);

        if ($exponent === 0) {
            return (string) $this->minor;
        }

        $sign = $this->minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($this->minor), $exponent + 1, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }

    public function toFloat(): float
    {
        return (float) $this->toMajor();
    }

    public function format(string $locale = 'en_IN'): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return $this->currency.' '.number_format($this->toFloat(), self::exponentFor($this->currency));
        }

        return (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency($this->toFloat(), $this->currency);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /** @return array{minor:int, currency:string, major:string} */
    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
            'major' => $this->toMajor(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Refusing to combine {$this->currency} with {$other->currency}. Convert first."
            );
        }
    }
}
