<!DOCTYPE html>
{{-- Booking voucher (M10.6, PRD P-6), rendered by VoucherService with dompdf. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $voucher['reference'] }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #16171A; }
        .header { background: #0B0B0C; color: #FFFFFF; padding: 18px 22px; }
        .header .company { font-size: 18px; font-weight: bold; }
        .header .title { color: #C8A45A; font-size: 12px; margin-top: 4px; }
        .reference { float: right; text-align: right; }
        .reference .value { font-size: 16px; font-weight: bold; color: #C8A45A; }
        h2 { font-size: 12px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #C8A45A; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 0; vertical-align: top; }
        td.label { width: 38%; color: #5B5E66; }
        td.amount { text-align: right; font-weight: bold; }
        .total td { border-top: 1px solid #D9D9DC; }
        .note { margin-top: 6px; color: #5B5E66; font-size: 10px; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #D9D9DC; font-size: 10px; color: #5B5E66; }
    </style>
</head>
<body>
    <div class="header">
        <div class="reference">
            <div>Booking reference</div>
            <div class="value">{{ $voucher['reference'] }}</div>
        </div>
        <div class="company">{{ $company->company_name }}</div>
        <div class="title">Booking voucher</div>
    </div>

    <h2>Booking</h2>
    <table>
        <tr><td class="label">Client</td><td>{{ $voucher['renter'] }}</td></tr>
        <tr><td class="label">Car</td><td>{{ $voucher['car'] }}</td></tr>
        @if ($voucher['days'] !== null)
            <tr><td class="label">Number of days</td><td>{{ $voucher['days'] }}</td></tr>
        @endif
    </table>

    <h2>Pick-up and return</h2>
    <table>
        <tr>
            <td class="label">Pick-up</td>
            <td>{{ $voucher['pickup']['date'] }} at {{ $voucher['pickup']['time'] }}<br>{{ $voucher['pickup']['point'] }}</td>
        </tr>
        <tr>
            <td class="label">Return</td>
            <td>{{ $voucher['return']['date'] }} at {{ $voucher['return']['time'] }}<br>{{ $voucher['return']['point'] }}</td>
        </tr>
    </table>

    <h2>Payment</h2>
    <table>
        <tr><td class="label">Total rental</td><td class="amount">{{ $voucher['total'] }}</td></tr>
        <tr><td class="label">Reservation paid</td><td class="amount">{{ $voucher['reservation_paid'] }}</td></tr>
        <tr class="total"><td class="label">Balance due at handover</td><td class="amount">{{ $voucher['balance'] }}</td></tr>
        @if ($voucher['security_deposit'] !== null)
            <tr><td class="label">Security deposit due at handover</td><td class="amount">{{ $voucher['security_deposit'] }}</td></tr>
        @endif
    </table>
    @if ($voucher['delivery_pending'])
        <p class="note">The delivery fee for your area is quoted by our team and added to the balance at handover.</p>
    @endif

    @if ($voucher['tiers'] !== [])
        <h2>Cancellation</h2>
        <table>
            @foreach ($voucher['tiers'] as $tier)
                <tr><td class="label">{{ $tier['label'] }}</td><td>{{ $tier['refund'] }} of the reservation refunded</td></tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        {{ $company->legal_name ?: $company->company_name }}<br>
        @if ($company->address) {{ $company->address }}<br> @endif
        @if ($company->phones !== []) {{ implode(', ', $company->phones) }}<br> @endif
        @if ($company->email) {{ $company->email }} @endif
    </div>
</body>
</html>
