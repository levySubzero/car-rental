<?php

namespace App\Models;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Models\Concerns\Sortable;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An extra on top of the daily rate (TECHNICAL_PLAN §8, PRD Q4/Q13). `applies` says when it goes
 * on a quote (D32); renters pick extras by `code`, which never changes once created. A
 * foreign-currency amount is converted to KES at the market rate when quoted. Only per-day and
 * per-booking charges payable at reservation or handover count towards the total.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property ChargeBasis $basis
 * @property int|null $amount_minor
 * @property string $currency_code
 * @property bool $tax_inclusive
 * @property ChargePayableAt $payable_at
 * @property ChargeApplies $applies
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory, LogsActivity, Sortable;

    protected $fillable = ['code', 'name', 'description', 'basis', 'amount_minor', 'currency_code', 'tax_inclusive', 'payable_at', 'applies', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basis' => ChargeBasis::class,
            'amount_minor' => 'integer',
            'tax_inclusive' => 'boolean',
            'payable_at' => ChargePayableAt::class,
            'applies' => ChargeApplies::class,
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
            ->useLogName('charges')
            ->logOnly(['code', 'name', 'description', 'basis', 'amount_minor', 'currency_code', 'tax_inclusive', 'payable_at', 'applies', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function amount(): ?Money
    {
        return $this->amount_minor === null ? null : new Money($this->amount_minor, $this->currency_code);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active charges renters can pick on the booking form.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOfferedAsExtra(Builder $query): void
    {
        $query->where('is_active', true)->where('applies', ChargeApplies::Extra);
    }
}
