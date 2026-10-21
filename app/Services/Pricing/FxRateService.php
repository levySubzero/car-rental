<?php

namespace App\Services\Pricing;

use App\Enums\FxProvider;
use App\Enums\FxRateSource;
use App\Exceptions\Domain\PricingException;
use App\Models\FxRate;
use App\Models\User;
use App\Services\Pricing\Fx\FxProviderException;
use App\Services\Pricing\Fx\FxRateProvider;
use App\Services\Pricing\Fx\FxRefreshResult;
use App\Services\Pricing\Fx\FxTable;
use App\Services\Pricing\Fx\ResolvedRate;
use App\Settings\FxSettings;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Exchange rates (M6.2–M6.4, D28). The API is the source of truth: a fresh API rate always
 * wins. A manual rate (with no expiry, or one still in the future) applies only while the
 * API rate is missing or older than FxSettings::stale_after_hours. After that the last API
 * rate is used, flagged stale. A currency with none of these has no rate and is hidden.
 */
class FxRateService
{
    // Cache key holding the outcome of the last refresh attempt, for the dashboard.
    public const LAST_REFRESH_KEY = 'fx.last_refresh';

    public function __construct(private readonly CurrencyService $currencies) {}

    /**
     * The rates in force now, with the current display margin applied.
     */
    public function table(): FxTable
    {
        $settings = $this->settings();
        $margin = Decimal::round(Decimal::div((string) $settings->margin_minor, '100'), 2);
        $freshAfter = now()->subHours($settings->stale_after_hours);
        $rates = [];

        foreach ($this->currencies->foreignCodes() as $code) {
            $api = FxRate::query()->where('currency_code', $code)->where('source', FxRateSource::Api)->latestFirst()->first();

            $manual = FxRate::query()->where('currency_code', $code)->where('source', FxRateSource::Manual)
                ->where(fn ($query) => $query->whereNull('override_until')->orWhere('override_until', '>', now()))
                ->latestFirst()->first();

            $chosen = match (true) {
                $api !== null && $api->fetched_at->greaterThanOrEqualTo($freshAfter) => [$api, false],
                $manual !== null => [$manual, false],
                $api !== null => [$api, true],
                default => null,
            };

            if ($chosen !== null) {
                [$row, $stale] = $chosen;
                $rates[$code] = new ResolvedRate($code, $row->market_rate, $margin, $row->source, $row->fetched_at, $stale, $row->override_until);
            }
        }

        return new FxTable($this->currencies->baseCode(), $rates);
    }

    /**
     * Fetch rates from the configured provider and store them.
     *
     * Skipped when the provider is manual, or when the last attempt was less than
     * refresh_minutes ago (unless $force). A rate that moved more than max_jump_percent
     * since the last API rate is not stored unless $acceptJumps: a sudden swing is more
     * often a bad feed than a real move, and staff should look before prices change.
     */
    public function refresh(bool $force = false, bool $acceptJumps = false, ?User $actor = null): FxRefreshResult
    {
        $settings = $this->settings();
        $provider = app(FxRateProvider::class);

        if ($settings->provider === FxProvider::Manual->value) {
            return new FxRefreshResult(FxRefreshResult::STATUS_SKIPPED, 'The provider is manual: staff enter the rates.');
        }

        $last = $this->lastRefresh();

        if (! $force && $last !== null && CarbonImmutable::parse($last['at'])->isAfter(now()->subMinutes($settings->refresh_minutes))) {
            return new FxRefreshResult(FxRefreshResult::STATUS_SKIPPED, "Refreshed less than {$settings->refresh_minutes} minutes ago.");
        }

        $codes = $this->currencies->foreignCodes();

        try {
            $fetched = $provider->fetch($this->currencies->baseCode(), $codes);
        } catch (FxProviderException $exception) {
            report($exception);

            return $this->record(new FxRefreshResult(FxRefreshResult::STATUS_FAILED, $exception->getMessage()), $actor);
        }

        $stored = $rejected = [];

        DB::transaction(function () use ($fetched, $provider, $settings, $acceptJumps, &$stored, &$rejected) {
            foreach ($fetched->rates as $code => $rate) {
                $previous = FxRate::query()->where('currency_code', $code)->where('source', FxRateSource::Api)->latestFirst()->first();

                // The provider has not published anything newer since the last fetch.
                if ($previous !== null && $previous->fetched_at->equalTo($fetched->asOf) && Decimal::compare($previous->market_rate, $rate) === 0) {
                    continue;
                }

                if ($previous !== null && ! $acceptJumps) {
                    $change = Decimal::percentChange($previous->market_rate, $rate);

                    if (Decimal::compare($change, (string) $settings->max_jump_percent) > 0) {
                        $rejected[$code] = ['previous' => $previous->market_rate, 'fetched' => $rate, 'change' => Decimal::round($change, 2)];

                        continue;
                    }
                }

                FxRate::create([
                    'currency_code' => $code,
                    'market_rate' => $rate,
                    'source' => FxRateSource::Api,
                    'provider' => $provider->name(),
                    'fetched_at' => $fetched->asOf,
                ]);
                $stored[$code] = $rate;
            }
        });

        if ($rejected !== []) {
            Log::warning('FX refresh: rates rejected by the jump guard', $rejected);
        }

        $message = sprintf('%d rate(s) stored, %d unchanged, %d rejected.', count($stored), count($fetched->rates) - count($stored) - count($rejected), count($rejected));

        return $this->record(new FxRefreshResult(FxRefreshResult::STATUS_REFRESHED, $message, $stored, $rejected), $actor);
    }

    /**
     * A staff-entered rate. It applies only while no fresh API rate exists (D28).
     */
    public function setManualRate(string $code, string $rate, ?CarbonImmutable $until, User $actor): FxRate
    {
        if ($code === $this->currencies->baseCode()) {
            throw PricingException::manualRateForBase($code);
        }

        if (! in_array($code, $this->currencies->foreignCodes(), true)) {
            throw PricingException::inactiveCurrency($code);
        }

        $row = FxRate::create([
            'currency_code' => $code,
            'market_rate' => Decimal::round(Decimal::of($rate), 6),
            'source' => FxRateSource::Manual,
            'fetched_at' => now(),
            'override_by' => $actor->id,
            'override_until' => $until,
        ]);

        activity('fx')->causedBy($actor)->performedOn($row)
            ->withProperties(['code' => $code, 'rate' => $row->market_rate, 'until' => $until?->toIso8601String()])
            ->event('manual-rate')
            ->log("manual {$code} rate set");

        return $row;
    }

    /**
     * Ends a manual rate now. History is kept: the row's expiry is set to the present.
     */
    public function expireManualRate(FxRate $rate, User $actor): void
    {
        if ($rate->source !== FxRateSource::Manual) {
            return;
        }

        $rate->forceFill(['override_until' => now()])->save();

        activity('fx')->causedBy($actor)->performedOn($rate)
            ->withProperties(['code' => $rate->currency_code])
            ->event('manual-rate-ended')
            ->log("manual {$rate->currency_code} rate ended");
    }

    /**
     * @return Collection<int, FxRate>
     */
    public function history(int $perCurrency = 20): Collection
    {
        return FxRate::query()->with('overrider:id,name')->latestFirst()->limit($perCurrency * 10)->get();
    }

    /**
     * @return array{at: string, status: string, message: string, stored: array<string, string>, rejected: array<string, mixed>}|null
     */
    public function lastRefresh(): ?array
    {
        /** @var array{at: string, status: string, message: string, stored: array<string, string>, rejected: array<string, mixed>}|null */
        return Cache::get(self::LAST_REFRESH_KEY);
    }

    private function record(FxRefreshResult $result, ?User $actor): FxRefreshResult
    {
        Cache::forever(self::LAST_REFRESH_KEY, ['at' => now()->toIso8601String(), ...$result->toArray()]);

        $log = activity('fx')->withProperties($result->toArray())->event($result->failed() ? 'refresh-failed' : 'refreshed');

        if ($actor !== null) {
            $log->causedBy($actor);
        }

        $log->log($result->message);

        return $result;
    }

    private function settings(): FxSettings
    {
        return app(FxSettings::class);
    }
}
