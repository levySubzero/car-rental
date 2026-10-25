<?php

namespace App\Enums;

use App\Models\PaymentSubmission;

/**
 * How money reached Baines (M11C, TECHNICAL_PLAN §12.2): a gateway, a payment the renter
 * reported by hand, or cash and card taken at the desk when the car went out or came back.
 */
enum FinanceChannel: string
{
    case MpesaStk = 'mpesa_stk';
    case MpesaPaybill = 'mpesa_paybill';
    case MpesaManual = 'mpesa_manual';
    case Paystack = 'paystack';
    case CardManual = 'card_manual';
    case Bank = 'bank';
    case Cash = 'cash';
    case Handover = 'handover';

    public function label(): string
    {
        return match ($this) {
            self::MpesaStk => 'M-Pesa STK',
            self::MpesaPaybill => 'M-Pesa paybill',
            self::MpesaManual => 'M-Pesa (manual)',
            self::Paystack => 'Paystack',
            self::CardManual => 'Card (manual)',
            self::Bank => 'Bank transfer',
            self::Cash => 'Cash',
            self::Handover => 'At handover',
        };
    }

    /**
     * The gateway when the submission came from one, otherwise the method the renter chose.
     */
    public static function forSubmission(PaymentSubmission $submission, ?PaymentGateway $gateway = null): self
    {
        if ($gateway !== null) {
            return match ($gateway) {
                PaymentGateway::MpesaStk => self::MpesaStk,
                PaymentGateway::MpesaC2b => self::MpesaPaybill,
                PaymentGateway::Paystack => self::Paystack,
            };
        }

        return match ($submission->payment_method_code) {
            PaymentMethodCode::Mpesa => self::MpesaManual,
            PaymentMethodCode::Card => self::CardManual,
            PaymentMethodCode::Bank => self::Bank,
            PaymentMethodCode::Cash => self::Cash,
        };
    }
}
