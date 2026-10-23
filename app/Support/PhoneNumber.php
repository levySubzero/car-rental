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
}
