<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CategorisationRule extends Model
{
    use HasFactory;

    protected $table = 'categorisation_rules';

    protected $fillable = ['user_id', 'category_id', 'match_type', 'pattern', 'priority', 'times_applied'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function matches(string $normalisedMerchant): bool
    {
        return match ($this->match_type) {
            'starts_with' => Str::startsWith($normalisedMerchant, Str::lower($this->pattern)),
            // Rules are user-authored, but the delimiter is still escaped and errors are
            // swallowed so a bad pattern cannot take the import down.
            'regex' => (bool) @preg_match('/'.str_replace('/', '\/', $this->pattern).'/i', $normalisedMerchant),
            default => Str::contains($normalisedMerchant, Str::lower($this->pattern)),
        };
    }
}
