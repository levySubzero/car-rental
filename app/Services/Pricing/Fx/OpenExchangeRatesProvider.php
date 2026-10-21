<?php

namespace App\Services\Pricing\Fx;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Open Exchange Rates (https://docs.openexchangerates.org), called directly over HTTP.
 *
 * Quotes are always USD-based (other bases need a paid plan), so the base-currency rate of
 * each code is the cross rate rate[base] ÷ rate[code], worked out with bcmath. Any doubt
 * about the response (wrong base, a missing or non-positive rate, a timestamp in the
 * future) rejects the whole fetch: a missing rate is better than a wrong one.
 */
class OpenExchangeRatesProvider implements FxRateProvider
{
    public const URL = 'https://openexchangerates.org/api/latest.json';

    // How far ahead of our clock the provider's timestamp may be before we distrust it.
    private const CLOCK_SKEW_SECONDS = 300;

    public function __construct(private readonly ?string $appId) {}

    public function name(): string
    {
        return 'open_exchange_rates';
    }

    public function fetch(string $base, array $codes): FxFetchResult
    {
        if (blank($this->appId)) {
            throw FxProviderException::missingKey($this->name());
        }

        if ($codes === []) {
            return FxFetchResult::empty();
        }

        $symbols = array_values(array_unique([$base, ...$codes]));

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->retry(2, 500, throw: false)
                ->get(self::URL, [
                    'app_id' => $this->appId,
                    'symbols' => implode(',', $symbols),
                    'prettyprint' => 'false',
                ]);
        } catch (ConnectionException $exception) {
            throw FxProviderException::request($this->name(), $exception->getMessage());
        }

        if ($response->failed()) {
            // The API explains errors in "description"; the key itself never appears there.
            $reason = $response->json('description') ?? $response->json('message') ?? 'HTTP '.$response->status();

            throw FxProviderException::request($this->name(), (string) $reason);
        }

        return $this->parse($response->json(), $base, $codes);
    }

    /**
     * @param  list<string>  $codes
     */
    private function parse(mixed $body, string $base, array $codes): FxFetchResult
    {
        if (! is_array($body) || ! isset($body['base'], $body['timestamp'], $body['rates']) || ! is_array($body['rates'])) {
            throw FxProviderException::invalid($this->name(), 'the response is missing base, timestamp or rates');
        }

        if ($body['base'] !== 'USD') {
            throw FxProviderException::invalid($this->name(), "expected USD-based rates, got {$body['base']}");
        }

        if (! is_int($body['timestamp'])) {
            throw FxProviderException::invalid($this->name(), 'the timestamp is not a number');
        }

        $asOf = CarbonImmutable::createFromTimestampUTC($body['timestamp']);

        if ($asOf->isAfter(now()->addSeconds(self::CLOCK_SKEW_SECONDS))) {
            throw FxProviderException::invalid($this->name(), 'the timestamp is in the future');
        }

        $usd = $this->rates($body['rates'], [$base, ...$codes]);
        $rates = [];

        foreach ($codes as $code) {
            $rates[$code] = Decimal::round(Decimal::div($usd[$base], $usd[$code]), 6);
        }

        return new FxFetchResult($rates, $asOf);
    }

    /**
     * Units per USD for each code, as exact decimal strings.
     *
     * @param  array<mixed>  $raw
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function rates(array $raw, array $codes): array
    {
        $rates = ['USD' => '1'];

        foreach ($codes as $code) {
            if ($code === 'USD' && ! array_key_exists('USD', $raw)) {
                continue;
            }

            if (! array_key_exists($code, $raw) || ! (is_int($raw[$code]) || is_float($raw[$code]))) {
                throw FxProviderException::invalid($this->name(), "no rate for {$code}");
            }

            try {
                $rate = Decimal::of($raw[$code]);
            } catch (InvalidArgumentException) {
                throw FxProviderException::invalid($this->name(), "the {$code} rate is not a number");
            }

            if (! Decimal::isPositive($rate)) {
                throw FxProviderException::invalid($this->name(), "the {$code} rate is not positive");
            }

            $rates[$code] = $rate;
        }

        return $rates;
    }
}
