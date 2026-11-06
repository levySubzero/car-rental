<?php

namespace App\Enums;

/**
 * Where a booking came from: the website, or staff entering a client who did not book online.
 */
enum BookingSource: string
{
    case Website = 'website';
    case Admin = 'admin';
}
