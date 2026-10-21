<?php

namespace App\Models;

use App\Models\Concerns\Sortable;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Where the car can be delivered and what it costs (PRD Q10). A null fee means staff quote it;
 * the quote then lists delivery without counting it (D29). One zone is the default.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $fee_minor
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory, LogsActivity, Sortable;

    protected $fillable = ['name', 'description', 'fee_minor', 'is_default', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_minor' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('delivery-zones')
            ->logOnly(['name', 'description', 'fee_minor', 'is_default', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function fee(): ?Money
    {
        return $this->fee_minor === null ? null : new Money($this->fee_minor);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
