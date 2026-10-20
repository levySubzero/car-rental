<?php

namespace App\Models;

use App\Enums\PaymentMethodCode;
use App\Models\Concerns\Sortable;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How a renter pays the reservation (PRD P-1). The set is fixed by PaymentMethodCode (D25).
 * `instructions` is Markdown, shown only after the renter picks the method (M9).
 * `metadata` holds the details staff fill in: paybill, till, bank account, card link (D20).
 *
 * @property int $id
 * @property PaymentMethodCode $code
 * @property string $name
 * @property string $instructions
 * @property bool $requires_transaction_message
 * @property bool $requires_proof
 * @property bool $is_active
 * @property int $sort_order
 * @property ArrayObject<string, string>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, LogsActivity, Sortable;

    protected $fillable = ['code', 'name', 'instructions', 'requires_transaction_message', 'requires_proof', 'is_active', 'metadata'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => PaymentMethodCode::class,
            'requires_transaction_message' => 'boolean',
            'requires_proof' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => AsArrayObject::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // The code is an enum; URLs need its string value.
    public function getRouteKey(): string
    {
        return $this->code->value;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment-methods')
            ->logOnly(['name', 'instructions', 'requires_transaction_message', 'requires_proof', 'is_active', 'metadata'])
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
