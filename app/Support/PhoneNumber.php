<?php

namespace App\Support;

/**
 * Phone numbers as digits in international form, for wa.me links and for finding a renter's
 * bookings by phone (M10B). Numbers written locally ("0712 345 678") are taken as Kenyan.
 */
final class PhoneNumber
{
    public static function digits(?string $number): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $number);

        return str_starts_with($digits, '0') ? '254'.substr($digits, 1) : $digits;
    }

    /**
     * A Safaricom-style mobile number as M-Pesa takes it (2547XXXXXXXX or 2541XXXXXXXX), or
     * null when the number is not a Kenyan mobile (M11B). "712 345 678" without the leading
     * zero is accepted too.
     */
    public static function kenyanMobile(?string $number): ?string
    {
        $digits = self::digits($number);

        if (preg_match('/^[71]\d{8}$/', $digits) === 1) {
            $digits = '254'.$digits;
        }

        return preg_match('/^254[71]\d{8}$/', $digits) === 1 ? $digits : null;
    }
}
