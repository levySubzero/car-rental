<?php

namespace App\Services\Cms;

use App\Models\Policy;
use App\Models\PolicyBundle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Policy bundles (D24): every publish snapshots the published version of each live policy,
 * so a booking can record exactly which texts the renter accepted (M8.14).
 */
class PolicyBundleService
{
    /**
     * The bundle a renter accepts now, or null before anything was published.
     */
    public function current(): ?PolicyBundle
    {
        return PolicyBundle::query()->orderByDesc('version')->first();
    }

    public function snapshot(?User $actor = null): PolicyBundle
    {
        return DB::transaction(function () use ($actor) {
            $next = (int) PolicyBundle::query()->lockForUpdate()->max('version') + 1;

            $versionIds = Policy::query()
                ->with('published:id,policy_id,version')
                ->get()
                ->map(fn (Policy $policy) => $policy->published?->id)
                ->filter()
                ->values()
                ->all();

            $bundle = PolicyBundle::create([
                'version' => $next,
                'published_at' => now(),
                'created_by' => $actor?->id,
            ]);
            $bundle->versions()->attach($versionIds);

            activity('policies')->causedBy($actor)
                ->performedOn($bundle)
                ->withProperties(['bundle' => $next, 'versions' => $versionIds])
                ->event('bundled')
                ->log("policy bundle {$next} published");

            return $bundle;
        });
    }
}
