<?php

namespace App\Http\Controllers\Public;

use App\Enums\ChargeApplies;
use App\Enums\PolicyGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PolicyResource;
use App\Models\CancellationTier;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Models\DepositBand;
use App\Models\Policy;
use App\Services\Cms\PageContent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Rental Policy page (M7.14): the published policies grouped, with an in-page index,
 * and the live pricing rules (cancellation tiers, fees, deposits, delivery) beside them,
 * so the page always matches what the booking flow charges.
 */
class RentalPolicyController extends Controller
{
    public function __invoke(PageContent $pages): Response
    {
        $page = $pages->for('rental-policy');

        $policies = Policy::query()->whereHas('published')->with('published')->ordered()->get()
            ->groupBy(fn (Policy $policy) => $policy->group->value);

        $groups = collect(PolicyGroup::cases())
            ->filter(fn (PolicyGroup $group) => $policies->has($group->value))
            ->map(fn (PolicyGroup $group) => [
                'key' => $group->value,
                'label' => $group->label(),
                'policies' => PolicyResource::collection($policies[$group->value])->resolve(),
            ])
            ->values();

        return Inertia::render('public/rental-policy', [
            'heading' => PageContent::text($page, 'heading', 'Rental policy'),
            'intro' => PageContent::text($page, 'intro'),
            'header_image' => $page['header_image'] ?? null,
            'groups' => $groups,
            'cancellation_tiers' => CancellationTier::query()->orderByDesc('hours_before_pickup')->get()
                ->map(fn (CancellationTier $tier) => [
                    'hours_before_pickup' => $tier->hours_before_pickup,
                    'refund_percent' => $tier->refund_percent_bp / 100,
                    'label' => $tier->label,
                ]),
            'fees' => Charge::query()->active()
                ->whereIn('applies', [ChargeApplies::Extra, ChargeApplies::Always, ChargeApplies::Listed])
                ->ordered()->get()
                ->map(fn (Charge $charge) => [
                    'code' => $charge->code,
                    'name' => $charge->name,
                    'description' => $charge->description,
                    'amount' => $charge->amount(),
                    'basis' => $charge->basis->label(),
                    'payable_at' => $charge->payable_at->label(),
                ]),
            'deposit_bands' => DepositBand::query()->active()->orderBy('min_age')->get()
                ->map(fn (DepositBand $band) => [
                    'ages' => $band->min_age === $band->max_age ? (string) $band->min_age : "{$band->min_age}–{$band->max_age}",
                    'percent' => $band->percent_bp / 100,
                    'label' => $band->label,
                ]),
            'delivery_zones' => DeliveryZone::query()->active()->ordered()->get()
                ->map(fn (DeliveryZone $zone) => [
                    'name' => $zone->name,
                    'description' => $zone->description,
                    'fee' => $zone->fee(),
                ]),
        ]);
    }
}
