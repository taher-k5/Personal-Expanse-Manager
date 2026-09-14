<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategorisationRule;
use App\Models\Transaction;
use App\Models\User;

/**
 * Guesses a category from the merchant, and learns from corrections.
 *
 * Rules the user created explicitly win. Below those, the app falls back to what the user
 * has historically filed this merchant under — which means the second Swiggy order is
 * categorised for free, without anyone writing a rule.
 */
final class AutoCategoriser
{
    public function suggest(User $user, ?string $merchant): ?int
    {
        $normalised = InsightService::normaliseMerchant($merchant);

        if ($normalised === null) {
            return null;
        }

        $rule = $user->categorisationRules()
            ->orderBy('priority')
            ->get()
            ->first(fn (CategorisationRule $rule): bool => $rule->matches($normalised));

        if ($rule !== null) {
            $rule->increment('times_applied');

            return $rule->category_id;
        }

        return $this->mostUsedCategoryFor($user, $normalised);
    }

    private function mostUsedCategoryFor(User $user, string $normalised): ?int
    {
        $categoryId = Transaction::query()
            ->where('user_id', $user->id)
            ->where('normalised_merchant', $normalised)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as hits')
            ->groupBy('category_id')
            ->orderByDesc('hits')
            ->value('category_id');

        return $categoryId === null ? null : (int) $categoryId;
    }

    /**
     * Called when someone re-files a transaction. After the same correction happens twice,
     * write a rule so it stops happening a third time.
     */
    public function learnFrom(Transaction $transaction): void
    {
        if ($transaction->normalised_merchant === null || $transaction->category_id === null) {
            return;
        }

        $sameChoice = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('normalised_merchant', $transaction->normalised_merchant)
            ->where('category_id', $transaction->category_id)
            ->count();

        if ($sameChoice < 2) {
            return;
        }

        CategorisationRule::updateOrCreate(
            [
                'user_id' => $transaction->user_id,
                'pattern' => $transaction->normalised_merchant,
            ],
            [
                'category_id' => $transaction->category_id,
                'match_type' => 'contains',
                'priority' => 50,
            ],
        );
    }
}
