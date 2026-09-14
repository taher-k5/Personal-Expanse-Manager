<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'parent_id', 'name', 'slug', 'kind', 'icon',
        'colour', 'is_essential', 'sort_order', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_essential' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->slug ??= Str::slug($category->name);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function scopeExpenses(Builder $query): void
    {
        $query->where('kind', 'expense')->where('is_archived', false);
    }

    /** "Food › Eating out" reads better than "Eating out" in a flat dropdown. */
    public function getFullNameAttribute(): string
    {
        return $this->parent
            ? "{$this->parent->name} › {$this->name}"
            : $this->name;
    }
}
