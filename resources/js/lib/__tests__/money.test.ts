import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import { convertMinor, displayPrice, formatMoney } from '../money';

type Amounts = Record<string, string | null>;
type FixtureCase = {
    name: string;
    fx?: Record<string, string>;
    margin?: string;
    expect: {
        error?: string;
        total?: string;
        reservation?: string;
        balance?: string;
        deposit?: string | null;
        display?: Record<string, Amounts>;
    };
};

// The same fixtures the PHP quote engine is tested against (tests/fixtures/quotes.json).
const fixture = JSON.parse(
    readFileSync(
        new URL('../../../../tests/fixtures/quotes.json', import.meta.url),
        'utf8',
    ),
) as {
    _meta: { defaults: { fx: Record<string, string>; margin: string } };
    cases: FixtureCase[];
};

const minor = (major: string) => Math.round(Number(major) * 100);

// market − margin, exactly, as the server shares it.
function effective(market: string, margin: string): string {
    const toMicros = (value: string) => {
        const [whole, fraction = ''] = value.split('.');

        return BigInt(whole + fraction.padEnd(6, '0').slice(0, 6));
    };
    const micros = toMicros(market) - toMicros(margin);
    const text = micros.toString().padStart(7, '0');

    return `${text.slice(0, -6)}.${text.slice(-6)}`;
}

describe('convertMinor', () => {
    const cases = fixture.cases.filter((c) => !c.expect.error);

    it.each(cases.map((c) => [c.name, c] as const))(
        'matches the server conversion: %s',
        (_name, c) => {
            const fx = c.fx ?? fixture._meta.defaults.fx;
            const margin = c.margin ?? fixture._meta.defaults.margin;

            for (const [code, amounts] of Object.entries(
                c.expect.display ?? {},
            )) {
                const rate = effective(fx[code], margin);

                for (const key of [
                    'total',
                    'reservation',
                    'balance',
                    'deposit',
                ] as const) {
                    const kes = c.expect[key];
                    const shown = amounts[key];

                    if (kes == null || shown == null) {
                        continue;
                    }

                    expect(convertMinor(minor(kes), rate)).toBe(minor(shown));
                }
            }
        },
    );

    it('converts the PRD example total', () => {
        expect(convertMinor(6_635_000, '126.000000')).toBe(52_659);
    });

    it('rejects malformed and non-positive rates', () => {
        expect(() => convertMinor(100, '1e3')).toThrow();
        expect(() => convertMinor(100, '0')).toThrow();
        expect(() => convertMinor(100, '1.0000001')).toThrow();
    });
});

describe('formatMoney', () => {
    it('shows cents only when the currency or amount needs them', () => {
        expect(formatMoney(6_635_000, { symbol: 'KSh', decimals: 0 })).toBe(
            'KSh 66,350',
        );
        expect(formatMoney(1_658_750, { symbol: 'KSh', decimals: 0 })).toBe(
            'KSh 16,587.50',
        );
        expect(formatMoney(52_659, { symbol: '$', decimals: 2 })).toBe(
            '$ 526.59',
        );
        expect(formatMoney(50_000, { symbol: '$', decimals: 2 })).toBe(
            '$ 500.00',
        );
    });
});

describe('displayPrice', () => {
    const shared = {
        selected: 'USD',
        options: [
            {
                code: 'KES',
                name: 'Kenya Shilling',
                symbol: 'KSh',
                decimals: 0,
                effective: null,
            },
            {
                code: 'USD',
                name: 'US Dollar',
                symbol: '$',
                decimals: 2,
                effective: '126.000000',
            },
        ],
    };

    it('converts to the selected currency', () => {
        expect(displayPrice(6_635_000, shared)).toBe('$ 526.59');
    });

    it('falls back to the base currency for an unknown selection', () => {
        expect(displayPrice(6_635_000, { ...shared, selected: 'XYZ' })).toBe(
            'KSh 66,350',
        );
    });
});
