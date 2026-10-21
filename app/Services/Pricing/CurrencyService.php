<?php

namespace App\Services\Pricing;

use App\Exceptions\Domain\PricingException;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\User;
use App\Settings\PricingSettings;
use Illuminate\Database\Eloquent\Collection;

/**
 * Display currencies (M6.1). The base currency (PricingSettings::base_currency, KES) is the
 * one prices are set and charged in; it is always active and has no exchange rate.
 */
class CurrencyService
{
    /**
     * @return Collection<int, Currency>
     */
    public function all(): Collection
    {
        return Currency::query()->ordered()->get();
    }

    /**
     * Every known code, active or not, base first in the admin's order.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_values(Currency::query()->ordered()->pluck('code')->all());
    }

    /**
     * @return Collection<int, Currency>
     */
    public function active(): Collection
    {
        return Currency::query()->active()->ordered()->get();
    }

    /**
     * @return list<string>
     */
    public function activeCodes(): array
    {
        return array_values(Currency::query()->active()->ordered()->pluck('code')->all());
    }

    /**
     * Active currencies other than the base: the ones that need an exchange rate.
     *
     * @return list<string>
     */
    public function foreignCodes(): array
    {
        return array_values(array_diff($this->activeCodes(), [$this->baseCode()]));
    }

    public function baseCode(): string
    {
        return app(PricingSettings::class)->base_currency;
    }

    public function isBase(Currency $currency): bool
    {
        return $currency->code === $this->baseCode();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values($this->active()
            ->map(fn (Currency $currency) => ['value' => $currency->code, 'label' => "{$currency->code} — {$currency->name}"])
            ->all());
    }

    /**
     * @param  array{code: string, name: string, symbol: string, decimals: int, is_active: bool}  $data
     */
    public function create(array $data): Currency
    {
        return Currency::create($data);
    }

    /**
     * @param  array{name: string, symbol: string, decimals: int, is_active: bool}  $data
     */
    public function update(Currency $currency, array $data): Currency
    {
        if (! $data['is_active']) {
            $this->assertCanDeactivate($currency);
        }

        $currency->update($data);

        return $currency;
    }

    public function setActive(Currency $currency, bool $active): void
    {
        if (! $active) {
            $this->assertCanDeactivate($currency);
        }

        $currency->update(['is_active' => $active]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids, User $actor): void
    {
        Currency::reorder($ids);

        activity('currencies')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('currencies reordered');
    }

    private function assertCanDeactivate(Currency $currency): void
    {
        if ($this->isBase($currency)) {
            throw PricingException::baseCurrencyLocked($currency->code);
        }

        // Active charges in this currency would have no rate to convert them with.
        $charges = Charge::query()->active()->where('currency_code', $currency->code)->count();

        if ($charges > 0) {
            throw PricingException::currencyInUse($currency->code, $charges);
        }
    }
}
