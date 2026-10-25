<?php

namespace App\Services\Cms;

use App\Enums\FxProvider;
use App\Enums\PaymentMethodCode;
use App\Enums\Permission;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Policy;
use App\Models\User;
use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\Fx\FxRefreshResult;
use App\Services\Pricing\FxRateService;
use App\Settings\FxSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use App\Support\Content\PageRegistry;
use App\Support\Markdown;

/**
 * What is still missing before launch (M5.11, D26): empty settings, the online payment
 * gateways (M11B.10), unpublished policies, preset pages without copy, and exchange rates that are missing,
 * stale or held back (M6.4). Each item is shown only to staff who
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
        private readonly FxRateService $fx,
        private readonly CurrencyService $currencies,
        private readonly DarajaClient $daraja,
        private readonly PaystackClient $paystack,
        private readonly OnlinePaymentService $onlinePayments,
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
            ...($user->can(Permission::FxRatesView) ? $this->exchangeRates() : []),
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
     * Online payments (M11B.10, D43): the only payment details launch needs are the gateways
     * set up and switched on. M-Pesa needs the Daraja keys, the paybill URLs registered with
     * Safaricom (not for a till) and the online switch; card needs the Paystack keys and the
     * switch. Bank and cash instructions are not checked.
     *
     * @return list<ChecklistItem>
     */
    private function paymentMethods(): array
    {
        $items = [];

        $mpesa = PaymentMethod::query()->where('code', PaymentMethodCode::Mpesa)->first();
        $missing = array_keys(array_filter([
            'Daraja keys (DARAJA_* on the server)' => ! $this->daraja->configured(),
            'paybill payments registered (php artisan mpesa:register-c2b)' => $this->daraja->configured() && ! $this->daraja->isTill() && ! $this->onlinePayments->c2bRegistered(),
            'online payments switched on' => ! ($mpesa->online_enabled ?? false),
        ]));

        if ($missing !== []) {
            $items[] = $this->item('payments.mpesa', 'Payments', 'M-Pesa online payments', 'Missing: '.implode(', ', $missing).'.', $mpesa ? route('admin.payment-methods.edit', $mpesa) : route('admin.payment-methods.index'));
        }

        $card = PaymentMethod::query()->where('code', PaymentMethodCode::Card)->first();
        $missing = array_keys(array_filter([
            'Paystack keys (PAYSTACK_* on the server)' => ! $this->paystack->configured(),
            'online payments switched on' => ! ($card->online_enabled ?? false),
        ]));

        if ($missing !== []) {
            $items[] = $this->item('payments.paystack', 'Payments', 'Card payments (Paystack)', 'Missing: '.implode(', ', $missing).'.', $card ? route('admin.payment-methods.edit', $card) : route('admin.payment-methods.index'));
        }

        return $items;
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
     * @return list<ChecklistItem>
     */
    private function exchangeRates(): array
    {
        $url = route('admin.fx-rates.index');
        $settings = app(FxSettings::class);
        $items = [];

        if ($settings->provider !== FxProvider::Manual->value && blank(config('services.fx.api_key'))) {
            $items[] = $this->item('fx.key', 'Exchange rates', 'Exchange rate API key', 'The provider is an API but FX_API_KEY is not set, so no rates are fetched.', $url);
        }

        $table = $this->fx->table();
        $missing = array_values(array_filter($this->currencies->foreignCodes(), fn (string $code) => ! $table->has($code)));
        $stale = array_keys(array_filter($table->rates, fn ($rate) => $rate->stale));

        if ($missing !== []) {
            $items[] = $this->item('fx.missing', 'Exchange rates', 'Missing exchange rates', 'No rate for '.implode(', ', $missing).': prices cannot be shown in it.', $url);
        }

        if ($stale !== []) {
            $items[] = $this->item('fx.stale', 'Exchange rates', 'Stale exchange rates', 'Older than '.$settings->stale_after_hours.' hours: '.implode(', ', $stale).'.', $url);
        }

        $last = $this->fx->lastRefresh();

        if ($last !== null && $last['status'] === FxRefreshResult::STATUS_FAILED) {
            $items[] = $this->item('fx.failed', 'Exchange rates', 'Exchange rate refresh failed', $last['message'], $url);
        }

        if ($last !== null && $last['rejected'] !== []) {
            $items[] = $this->item('fx.rejected', 'Exchange rates', 'Exchange rates held back', 'Moved more than '.$settings->max_jump_percent.'% since the last rate: '.implode(', ', array_keys($last['rejected'])).'. Review and refresh accepting the change.', $url);
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
