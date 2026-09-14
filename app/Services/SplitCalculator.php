<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SplitMethod;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Turns "who was there and how should this be divided" into exact per-person amounts.
 *
 * Every method returns an array keyed by group member id whose values always sum back to
 * the original total. That invariant is what keeps a group ledger from drifting.
 */
final class SplitCalculator
{
    /**
     * @param  array<int, float|int>|array<int, string>  $input  keyed by group member id
     * @return array<int, Money>
     */
    public static function for(SplitMethod $method, Money $total, array $input): array
    {
        return match ($method) {
            SplitMethod::None => [],
            SplitMethod::Equal => self::equal($total, array_keys($input)),
            SplitMethod::Shares => self::shares($total, $input),
            SplitMethod::Percentage => self::percentage($total, $input),
            SplitMethod::Exact => self::exact($total, $input),
            SplitMethod::Adjustment => self::adjustment($total, $input),
        };
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array<int, Money>
     */
    public static function equal(Money $total, array $memberIds): array
    {
        if ($memberIds === []) {
            throw new InvalidArgumentException('Pick at least one person to split with.');
        }

        return $total->allocate(array_fill_keys($memberIds, 1));
    }

    /**
     * Weighted split. Three friends where one brought a partner: [1, 1, 2].
     *
     * @param  array<int, float|int>  $sharesByMember
     * @return array<int, Money>
     */
    public static function shares(Money $total, array $sharesByMember): array
    {
        $shares = array_filter($sharesByMember, fn ($v): bool => (float) $v > 0);

        if ($shares === []) {
            throw new InvalidArgumentException('Give at least one person a share above zero.');
        }

        return $total->allocate(array_map('floatval', $shares));
    }

    /**
     * @param  array<int, float|int>  $percentByMember
     * @return array<int, Money>
     */
    public static function percentage(Money $total, array $percentByMember): array
    {
        $sum = round(array_sum(array_map('floatval', $percentByMember)), 4);

        if (abs($sum - 100.0) > 0.01) {
            throw new InvalidArgumentException(
                sprintf('Percentages add up to %s%%. They need to add up to 100%%.', rtrim(rtrim(number_format($sum, 2), '0'), '.'))
            );
        }

        return $total->allocate(array_map('floatval', $percentByMember));
    }

    /**
     * Exact amounts, entered in major units. Validated against the total so a typo cannot
     * silently create or destroy money.
     *
     * @param  array<int, float|int|string|Money>  $amountsByMember
     * @return array<int, Money>
     */
    public static function exact(Money $total, array $amountsByMember): array
    {
        $amounts = [];

        foreach ($amountsByMember as $memberId => $amount) {
            $amounts[$memberId] = $amount instanceof Money
                ? $amount
                : Money::fromMajor($amount, $total->currency);
        }

        $sum = Money::sum($amounts, $total->currency);

        if (! $sum->equals($total)) {
            $difference = $total->minus($sum);
            $verb = $difference->isPositive() ? 'short by' : 'over by';

            throw new InvalidArgumentException(
                sprintf('The amounts are %s %s.', $verb, $difference->absolute()->format())
            );
        }

        return $amounts;
    }

    /**
     * Split equally, but first give named people their own extras.
     *
     * The standard case: a shared dinner bill where two people also ordered cocktails.
     * Each adjustment is charged to that person in full, then whatever is left over is
     * divided equally across everyone at the table.
     *
     * @param  array<int, float|int|string>  $adjustmentsByMember  keyed by member id, in major units
     * @return array<int, Money>
     */
    public static function adjustment(Money $total, array $adjustmentsByMember): array
    {
        if ($adjustmentsByMember === []) {
            throw new InvalidArgumentException('Pick at least one person to split with.');
        }

        $extras = [];
        foreach ($adjustmentsByMember as $memberId => $value) {
            $extras[$memberId] = Money::fromMajor($value ?: 0, $total->currency);
        }

        $extrasTotal = Money::sum($extras, $total->currency);
        $remainder = $total->minus($extrasTotal);

        if ($remainder->isNegative()) {
            throw new InvalidArgumentException(
                sprintf('The extras come to %s, which is more than the bill.', $extrasTotal->format())
            );
        }

        $shared = $remainder->allocate(array_fill_keys(array_keys($extras), 1));

        $result = [];
        foreach ($extras as $memberId => $extra) {
            $result[$memberId] = $extra->plus($shared[$memberId]);
        }

        return $result;
    }

    /**
     * Sanity check used before persisting splits.
     *
     * @param  array<int, Money>  $splits
     */
    public static function assertBalanced(Money $total, array $splits): void
    {
        $sum = Money::sum($splits, $total->currency);

        if (! $sum->equals($total)) {
            throw new InvalidArgumentException(
                "Split of {$sum->format()} does not match the total of {$total->format()}."
            );
        }
    }
}
