<?php

namespace App\Services\Cms;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use App\Support\Content\PageRegistry;
use App\Support\Markdown;

/**
 * What is still missing before launch (M5.11, D26): empty settings, TODO payment details,
 * unpublished policies and preset pages without copy. Each item is shown only to staff who
 * can fix it. M11.10 reuses this on the operations dashboard.
 *
 * @phpstan-type ChecklistItem array{key: string, area: string, label: string, detail: string, url: string}
 */
class SetupChecklistService
{
    public function __construct(
        private readonly PageRegistry $pages,
        private readonly GeneralSettings $general,
        private readonly HomeSettings $home,
    ) {}

    /**
     * @return list<ChecklistItem>
     */
    public function outstanding(User $user): array
    {
        return [
            ...($user->can(Permission::SettingsGeneralUpdate) ? $this->generalSettings() : []),
            ...($user->can(Permission::SettingsHomeUpdate) ? $this->homeSettings() : []),
            ...($user->can(Permission::PaymentMethodsView) ? $this->paymentMethods() : []),
            ...($user->can(Permission::PoliciesView) ? $this->policies() : []),
            ...($user->can(Permission::PagesView) ? $this->presetPages() : []),
        ];
    }

    /**
     * @return list<ChecklistItem>
     */
    private function generalSettings(): array
    {
        $missing = array_keys(array_filter([
            'legal name' => blank($this->general->legal_name),
            'phone number' => $this->general->phones === [],
            'WhatsApp number' => blank($this->general->whatsapp_number),
            'email' => blank($this->general->email),
            'address' => blank($this->general->address),
            'map' => blank($this->general->map_embed_url),
            'coordinates' => $this->general->latitude === null || $this->general->longitude === null,
            'opening hours' => $this->general->opening_hours === [],
        ]));

        return $missing === [] ? [] : [$this->item(
            'settings.general',
            'Settings',
            'Company and contact details',
            'Missing: '.implode(', ', $missing).'.',
            route('admin.settings.edit', 'general'),
        )];
    }

    /**
     * @return list<ChecklistItem>
     */
    private function homeSettings(): array
    {
        $missing = array_keys(array_filter([
            'hero image' => $this->home->hero_media_uuid === null,
            'trust figures' => $this->home->trust_figures === [],
        ]));

        return $missing === [] ? [] : [$this->item(
            'settings.home',
            'Settings',
            'Home page hero and figures',
            'Missing: '.implode(', ', $missing).'.',
            route('admin.settings.edit', 'home'),
        )];
    }

    /**
     * @return list<ChecklistItem>
     */
    private function paymentMethods(): array
    {
        return array_values(PaymentMethod::query()->active()->ordered()->get()
            ->filter(fn (PaymentMethod $method) => Markdown::hasTodo($method->instructions)
                || Markdown::hasTodo(implode("\n", $method->metadata?->getArrayCopy() ?? [])))
            ->map(fn (PaymentMethod $method) => $this->item(
                "payment-method.{$method->code->value}",
                'Payment methods',
                $method->name,
                'The instructions still contain TODO placeholders.',
                route('admin.payment-methods.edit', $method),
            ))
            ->all());
    }

    /**
     * @return list<ChecklistItem>
     */
    private function policies(): array
    {
        return array_values(Policy::query()->with(['published:id,policy_id,version', 'draft'])->ordered()->get()
            ->filter(fn (Policy $policy) => $policy->published === null || Markdown::hasConfirm($policy->draft?->body))
            ->map(fn (Policy $policy) => $this->item(
                "policy.{$policy->slug}",
                'Policies',
                $policy->title,
                Markdown::hasConfirm($policy->draft?->body)
                    ? 'The draft has a [CONFIRM] note to resolve before publishing.'
                    : 'Not published yet.',
                route('admin.policies.edit', $policy),
            ))
            ->all());
    }

    /**
     * @return list<ChecklistItem>
     */
    private function presetPages(): array
    {
        $rows = Page::query()->get()->keyBy('slug');
        $items = [];

        foreach ($this->pages->all() as $definition) {
            $page = $rows->get($definition->slug);
            $missing = $definition->missing($page?->content);

            if ($page !== null && $missing !== []) {
                $items[] = $this->item(
                    "page.{$definition->slug}",
                    'Pages',
                    $definition->title,
                    'Missing: '.mb_strtolower(implode(', ', $missing)).'.',
                    route('admin.pages.edit', $page),
                );
            }
        }

        return $items;
    }

    /**
     * @return ChecklistItem
     */
    private function item(string $key, string $area, string $label, string $detail, string $url): array
    {
        return compact('key', 'area', 'label', 'detail', 'url');
    }
}
