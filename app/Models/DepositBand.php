<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DepositBandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The refundable security deposit by driver age, as a share of the car value (PRD Q2).
 * Active bands never overlap; a driver whose age matches no band cannot book.
 *
 * @property int $id
 * @property int $min_age
 * @property int $max_age
 * @property int $percent_bp
 * @property string|null $label
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DepositBand extends Model
{
    /** @use HasFactory<DepositBandFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['min_age', 'max_age', 'percent_bp', 'label', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'percent_bp' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('deposit-bands')
            ->logOnly(['min_age', 'max_age', 'percent_bp', 'label', 'is_active'])
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
