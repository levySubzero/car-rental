<?php

namespace App\Models;

use App\Models\Concerns\Sortable;
use Carbon\CarbonImmutable;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A currency renters can view prices in. Prices are always charged in the base currency
 * (PricingSettings::base_currency, KES); the others are display conversions (PRD §5).
 * `decimals` is for display only: stored amounts always have 2 (D27).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimals
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, LogsActivity, Sortable;

    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('currencies')
            ->logOnly(['code', 'name', 'symbol', 'decimals', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
