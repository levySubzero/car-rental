<?php

namespace App\Models;

use App\Enums\FxRateSource;
use Carbon\CarbonImmutable;
use Database\Factories\FxRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exchange rate observation: KES per unit of `currency_code`. Rows are never updated;
 * FxRateService picks which one applies (D28). The display margin is not stored here.
 *
 * @property int $id
 * @property string $currency_code
 * @property string $market_rate decimal string, 6 places
 * @property FxRateSource $source
 * @property string|null $provider
 * @property CarbonImmutable $fetched_at
 * @property int|null $override_by
 * @property CarbonImmutable|null $override_until
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $overrider
 */
class FxRate extends Model
{
    /** @use HasFactory<FxRateFactory> */
    use HasFactory;

    protected $fillable = ['currency_code', 'market_rate', 'source', 'provider', 'fetched_at', 'override_by', 'override_until'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'market_rate' => 'decimal:6',
            'source' => FxRateSource::class,
            'fetched_at' => 'immutable_datetime',
            'override_until' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('fetched_at')->orderByDesc('id');
    }
}
