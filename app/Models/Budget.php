<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'name', 'amount_minor', 'currency', 'period',
        'starts_on', 'ends_on', 'rollover', 'rollover_balance_minor',
        'alert_threshold', 'alerted_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'alerted_at' => 'datetime',
            'rollover' => 'boolean',
            'is_active' => 'boolean',
            'alert_threshold' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function label(): string
    {
        return $this->name ?? $this->category?->name ?? 'Everything';
    }
}
