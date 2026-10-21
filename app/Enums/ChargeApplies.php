<?php

namespace App\Enums;

/**
 * When a charge goes on a quote (D32). Staff pick this per charge; code that needs to
 * recognise a particular charge (the chauffeur, an additional driver) uses its fixed `code`.
 */
enum ChargeApplies: string
{
    // Offered on the booking form; added when the renter picks it.
    case Extra = 'extra';

    // On every quote, whatever the renter picks.
    case Always = 'always';

    // Never on a quote: shown in the rental policy's fees table only.
    case Listed = 'listed';

    public function label(): string
    {
        return match ($this) {
            self::Extra => 'When the renter picks it',
            self::Always => 'On every booking',
            self::Listed => 'Never (listed on the policy page only)',
        };
    }
}
