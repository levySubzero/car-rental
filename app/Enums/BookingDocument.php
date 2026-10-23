<?php

namespace App\Enums;

/**
 * The KYC documents a driver may be asked for. Each is a private `booking.{value}` media
 * collection; BookingSettings::document_requirements says which are required.
 */
enum BookingDocument: string
{
    case IdFront = 'id_front';
    case IdBack = 'id_back';
    case Passport = 'passport';
    case KraCertificate = 'kra_certificate';
    case Licence = 'licence';

    public function label(): string
    {
        return match ($this) {
            self::IdFront => 'ID card, front',
            self::IdBack => 'ID card, back',
            self::Passport => 'Passport photo page',
            self::KraCertificate => 'KRA PIN certificate',
            self::Licence => 'Driving licence',
        };
    }

    public function collection(): string
    {
        return "booking.{$this->value}";
    }
}
