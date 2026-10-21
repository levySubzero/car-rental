/*
 * Display prices (M6.13, D31). Prices always travel as KES minor units; the browser shows
 * them in the renter's currency using the `effective` rate (market − margin) the server
 * shares. Conversion is exact integer maths with the same half-up rounding as the server
 * (App\Services\Pricing\Fx\FxTable::display), so SSR and the client agree to the cent.
 */

// Mirrors App\Services\Pricing\DisplayCurrencyService.
export type DisplayCurrency = {
    code: string;
    name: string;
    symbol: string;
    // Display decimals; stored amounts always have 2 (D27).
    decimals: number;
    // KES per unit after the margin, e.g. "126.000000"; null for the base currency.
    effective: string | null;
};

export type SharedCurrency = {
    selected: string;
    options: DisplayCurrency[];
};

const RATE_SCALE = 6;

// "126.5" → 126500000n (6 decimal places, exact).
function scaled(rate: string): bigint {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(rate.trim());

    if (!match) {
        throw new Error(`Invalid rate "${rate}"`);
    }

    const fraction = (match[2] ?? '').padEnd(RATE_SCALE, '0');

    if (
        fraction.length > RATE_SCALE &&
        /[^0]/.test(fraction.slice(RATE_SCALE))
    ) {
        throw new Error(
            `Rate "${rate}" has more than ${RATE_SCALE} decimal places`,
        );
    }

    return BigInt(match[1] + fraction.slice(0, RATE_SCALE));
}

/**
 * KES minor units → minor units of the currency, rounded half up: 6635000 at "126" → 52659.
 */
export function convertMinor(minorKes: number, effective: string): number {
    const rate = scaled(effective);

    if (rate <= 0n) {
        throw new Error('The rate must be positive');
    }

    const numerator = BigInt(Math.abs(minorKes)) * 10n ** BigInt(RATE_SCALE);
    const rounded = (numerator * 2n + rate) / (rate * 2n);

    return Number(minorKes < 0 ? -rounded : rounded);
}

/**
 * Format minor units for display: "KSh 66,350", "$ 526.59". Cents show when there are any,
 * even for a currency displayed without decimals, so no amount is silently rounded.
 */
export function formatMoney(
    minor: number,
    currency: Pick<DisplayCurrency, 'symbol' | 'decimals'>,
): string {
    const digits = minor % 100 === 0 ? currency.decimals : 2;
    const number = new Intl.NumberFormat('en-KE', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Math.abs(minor) / 100);

    return `${minor < 0 ? '-' : ''}${currency.symbol} ${number}`;
}

/**
 * A KES amount in the renter's selected currency, falling back to KES.
 */
export function displayPrice(minorKes: number, shared: SharedCurrency): string {
    const currency =
        shared.options.find((option) => option.code === shared.selected) ??
        shared.options.find((option) => option.effective === null);

    if (!currency) {
        return formatMoney(minorKes, { symbol: 'KES', decimals: 0 });
    }

    const minor =
        currency.effective === null
            ? minorKes
            : convertMinor(minorKes, currency.effective);

    return formatMoney(minor, currency);
}
