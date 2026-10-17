<?php

use App\Enums\RoundingMode;
use App\Support\Money;

it('parses major-unit input exactly', function (int|float|string $input, int $minor) {
    expect(Money::fromMajor($input)->minor)->toBe($minor);
})->with([
    'integer' => [2000, 200_000],
    'string' => ['66350', 6_635_000],
    'one decimal' => ['16587.5', 1_658_750],
    'two decimals' => ['0.01', 1],
    'trailing zeros' => ['10.500', 1050],
    'float' => [0.1 + 0.2, 30],
    'negative' => ['-5.25', -525],
]);

it('rejects malformed or over-precise input', function (string $input) {
    Money::fromMajor($input);
})->throws(InvalidArgumentException::class)->with(['abc', '1.234', '1,000', '']);

it('formats as an exact major-unit string', function () {
    expect(Money::fromMajor('16588')->toMajorString())->toBe('16588.00')
        ->and((new Money(-525))->toMajorString())->toBe('-5.25')
        ->and((new Money(7, 'KES', 0))->toMajorString())->toBe('7');
});

it('matches the PRD worked example for the reservation', function () {
    // PRD §5: total KES 66,350, reservation 25%, rounded up to the shilling.
    $total = Money::fromMajor(66_350);

    $reservation = $total->percentBp(2500, RoundingMode::CeilMajor);
    $balance = $total->subtract($reservation);

    expect($reservation->toMajorString())->toBe('16588.00')
        ->and($balance->toMajorString())->toBe('49762.00');
});

it('rounds percentages per mode', function (RoundingMode $mode, int $expected) {
    // 25% of 66.65 = 16.6625
    expect((new Money(6665))->percentBp(2500, $mode)->minor)->toBe($expected);
})->with([
    'floor' => [RoundingMode::Floor, 1666],
    'round' => [RoundingMode::Round, 1666],
    'ceil major' => [RoundingMode::CeilMajor, 1700],
]);

it('rounds half up', function () {
    // 50% of 0.03 = 0.015 → 0.02
    expect((new Money(3))->percentBp(5000, RoundingMode::Round)->minor)->toBe(2);
});

it('leaves exact whole amounts alone when ceiling', function () {
    expect(Money::fromMajor(100)->percentBp(2500, RoundingMode::CeilMajor)->minor)->toBe(2500);
});

it('computes a deposit band percentage of the car value', function () {
    // PRD §5: 2% of an 8,000,000 car = 160,000
    expect(Money::fromMajor(8_000_000)->percentBp(200, RoundingMode::Round)->toMajorString())->toBe('160000.00');
});

it('adds, subtracts and multiplies', function () {
    $rate = Money::fromMajor(15_000);

    expect($rate->multiply(4)->add(Money::fromMajor(2_000))->minor)->toBe(6_200_000)
        ->and($rate->subtract(Money::fromMajor(5_000))->minor)->toBe(1_000_000);
});

it('refuses to mix currencies', function () {
    Money::fromMajor(1)->add(Money::fromMajor(1, 'USD'));
})->throws(InvalidArgumentException::class);

it('serialises to minor, currency and major', function () {
    expect(json_encode(new Money(150)))->toBe('{"minor":150,"currency":"KES","major":"1.50"}');
});
