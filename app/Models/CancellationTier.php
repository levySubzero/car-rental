<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CancellationTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Refund share when a renter cancels at least `hours_before_pickup` before pickup (PRD Q14).
 * Empty until the client sets a policy; shown on the rental policy page and at cancellation.
 *
 * @property int $id
 * @property int $hours_before_pickup
 * @property int $refund_percent_bp
 * @property string|null $label
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CancellationTier extends Model
{
    /** @use HasFactory<CancellationTierFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['hours_before_pickup', 'refund_percent_bp', 'label'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours_before_pickup' => 'integer',
            'refund_percent_bp' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cancellation-tiers')
            ->logOnly(['hours_before_pickup', 'refund_percent_bp', 'label'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
