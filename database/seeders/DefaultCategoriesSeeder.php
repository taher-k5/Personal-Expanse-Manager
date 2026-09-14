<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter categories, seeded on signup.
 *
 * Deliberately short. A new user faced with forty categories files everything under
 * "Miscellaneous"; a user with twelve splits them properly and adds their own.
 */
class DefaultCategoriesSeeder extends Seeder
{
    public const CATEGORIES = [
        ['Rent', 'heroicon-o-home', '#1F4FD8', true],
        ['Groceries', 'heroicon-o-shopping-cart', '#0E7A4F', true],
        ['Eating out', 'heroicon-o-cake', '#D6741C', false],
        ['Transport', 'heroicon-o-truck', '#5B6676', false],
        ['Bills and utilities', 'heroicon-o-bolt', '#8A4FD8', true],
        ['Health', 'heroicon-o-heart', '#B42318', true],
        ['Shopping', 'heroicon-o-shopping-bag', '#C2377E', false],
        ['Entertainment', 'heroicon-o-film', '#1C93A8', false],
        ['Travel', 'heroicon-o-paper-airplane', '#2F6FD0', false],
        ['Education', 'heroicon-o-academic-cap', '#4C5A9E', false],
        ['Gifts and giving', 'heroicon-o-gift', '#A8551C', false],
        ['Everything else', 'heroicon-o-ellipsis-horizontal', '#9AA4B2', false],
    ];

    public function forUser(User $user): void
    {
        foreach (self::CATEGORIES as $index => [$name, $icon, $colour, $essential]) {
            $user->categories()->firstOrCreate(
                ['slug' => str($name)->slug()->value()],
                [
                    'name' => $name,
                    'kind' => 'expense',
                    'icon' => $icon,
                    'colour' => $colour,
                    'is_essential' => $essential,
                    'sort_order' => $index,
                ],
            );
        }

        $user->categories()->firstOrCreate(
            ['slug' => 'income'],
            ['name' => 'Income', 'kind' => 'income', 'icon' => 'heroicon-o-banknotes', 'colour' => '#0E7A4F'],
        );
    }

    public function run(): void
    {
        User::query()->each(fn (User $user) => $this->forUser($user));
    }
}
