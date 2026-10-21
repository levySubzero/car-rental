<?php

use App\Support\Decimal;

it('rounds half away from zero', function (string $value, int $scale, string $expected) {
    expect(Decimal::round($value, $scale))->toBe($expected);
})->with([
    ['126.5', 0, '127'],
    ['126.49999', 0, '126'],
    ['52658.730158', 0, '52659'],
    ['129.2500005', 6, '129.250001'],
    ['-0.125', 2, '-0.13'],
    ['0', 2, '0.00'],
]);

it('normalises floats from an API response without float noise', function () {
    expect(Decimal::of(3.672538))->toBe('3.672538000000')
        ->and(Decimal::of(0.1 + 0.2))->toBe('0.300000000000')
        ->and(Decimal::of('129.25'))->toBe('129.25');
});

it('rejects values that are not plain decimals', function (string $value) {
    Decimal::of($value);
})->with(['1e5', 'abc', '', '1,000'])->throws(InvalidArgumentException::class);

it('works out cross rates exactly', function () {
    // KES per GBP from USD-based quotes: 129.25 KES/USD ÷ 0.7891 GBP/USD.
    expect(Decimal::round(Decimal::div('129.25', '0.7891'), 6))->toBe('163.794196');
});

it('measures the percentage change between two rates', function () {
    expect(Decimal::round(Decimal::percentChange('129', '142'), 4))->toBe('10.0775')
        ->and(Decimal::round(Decimal::percentChange('129', '116'), 4))->toBe('10.0775');
});

it('refuses to divide by zero', function () {
    Decimal::div('1', '0.000');
})->throws(InvalidArgumentException::class);
