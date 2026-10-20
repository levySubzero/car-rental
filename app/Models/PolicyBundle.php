<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A snapshot of the published version of every policy at one moment (D24). Renters accept a
 * bundle (M8.14), so what they agreed to can always be shown later, word for word.
 *
 * @property int $id
 * @property int $version
 * @property CarbonImmutable $published_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PolicyBundle extends Model
{
    protected $fillable = ['version', 'published_at', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<PolicyVersion, $this>
     */
    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(PolicyVersion::class, 'policy_bundle_version');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
