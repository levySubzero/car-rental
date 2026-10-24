<?php

use App\Support\PhoneNumber;

it('reads local numbers as Kenyan', function (?string $input, string $digits) {
    expect(PhoneNumber::digits($input))->toBe($digits);
})->with([
    ['0712 345 678', '254712345678'],
    ['+254 712-345-678', '254712345678'],
    ['+44 20 7946 0958', '442079460958'],
    [null, ''],
]);

it('gives the form M-Pesa takes for Kenyan mobiles only', function (?string $input, ?string $msisdn) {
    expect(PhoneNumber::kenyanMobile($input))->toBe($msisdn);
})->with([
    ['0712345678', '254712345678'],
    ['0110 123 456', '254110123456'],
    ['+254712345678', '254712345678'],
    ['712345678', '254712345678'],
    ['254712345678', '254712345678'],
    ['0201234567', null],
    ['07123456', null],
    ['+44 7700 900123', null],
    ['', null],
    [null, null],
]);
