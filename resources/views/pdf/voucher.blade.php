<!DOCTYPE html>
{{-- Booking voucher (PRD P-6, the client's field list of 2026-11), rendered by VoucherService with dompdf. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $voucher['reference'] }}</title>
    <style>
        @page { margin: 28px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #16171A; line-height: 1.35; }
        .header { background: #0B0B0C; color: #FFFFFF; padding: 18px 22px; }
        .header .company { font-size: 18px; font-weight: bold; }
        .header .title { color: #C8A45A; font-size: 11px; margin-top: 4px; letter-spacing: 2px; text-transform: uppercase; }
        .reference { float: right; text-align: right; font-size: 9px; color: #BFC1C6; }
        .reference .value { font-size: 16px; font-weight: bold; color: #C8A45A; margin-top: 2px; }
        .label { color: #5B5E66; font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; }
        .value { font-size: 11px; font-weight: bold; margin-top: 2px; }
        h2 { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #8A6D2F; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #C8A45A; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; padding: 6px 10px 6px 0; vertical-align: top; }
        .trip td { width: 50%; padding: 10px 12px; vertical-align: top; background: #F6F4EF; border: 3px solid #FFFFFF; }
        .trip .when { font-size: 12px; font-weight: bold; margin-top: 3px; }
        .trip .where { color: #3A3C42; margin-top: 3px; }
        .money td { padding: 6px 0; border-bottom: 1px solid #ECECEE; }
        .money td.amount { text-align: right; font-weight: bold; font-size: 11px; }
        .money tr.balance td { border-bottom: none; border-top: 1.5px solid #16171A; font-size: 12px; }
        .muted { color: #5B5E66; font-size: 9px; }
        .nb { margin-top: 14px; padding: 10px 12px; border: 1.5px solid #C8A45A; background: #FBF7EE; }
        .nb .title { font-weight: bold; font-size: 9.5px; color: #8A6D2F; letter-spacing: 1px; }
        .nb p { margin: 4px 0 0; font-size: 9.5px; }
        .office td { padding: 3px 12px 3px 0; vertical-align: top; }
        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #D9D9DC; font-size: 8.5px; color: #5B5E66; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="reference">
            <div>BOOKING REFERENCE</div>
            <div class="value">{{ $voucher['reference'] }}</div>
        </div>
        <div class="company">{{ $company->company_name }}</div>
        <div class="title">Booking voucher</div>
    </div>

    <h2>Client and car</h2>
    <table class="grid">
        <tr>
            <td><div class="label">Client name</div><div class="value">{{ $voucher['renter'] }}</div></td>
            <td><div class="label">Car type</div><div class="value">{{ $voucher['car'] }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Phone number</div><div class="value">{{ $voucher['phone'] ?: '—' }}</div></td>
            <td><div class="label">Email</div><div class="value">{{ $voucher['email'] ?: '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Number of days</div><div class="value">{{ $voucher['days'] ?? '—' }}</div></td>
            <td><div class="label">Area of use</div><div class="value">{{ $voucher['area_of_use'] ?: '—' }}</div></td>
        </tr>
    </table>

    <h2>Pick-up and drop-off</h2>
    <table class="trip">
        <tr>
            <td>
                <div class="label">Pick-up</div>
                <div class="when">{{ $voucher['pickup']['date'] }}, {{ $voucher['pickup']['time'] }}</div>
                <div class="where">{{ $voucher['pickup']['point'] }}</div>
            </td>
            <td>
                <div class="label">Drop-off</div>
                <div class="when">{{ $voucher['return']['date'] }}, {{ $voucher['return']['time'] }}</div>
                <div class="where">{{ $voucher['return']['point'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Payment</h2>
    <table class="money">
        <tr><td>Total rental amount</td><td class="amount">{{ $voucher['total'] }}</td></tr>
        <tr><td>Deposit paid</td><td class="amount">{{ $voucher['reservation_paid'] }}</td></tr>
        <tr class="balance"><td>Balance due at handover</td><td class="amount">{{ $voucher['balance'] }}</td></tr>
    </table>
    @if ($voucher['delivery_pending'])
        <p class="muted">The delivery fee for your area is quoted by our team and added to the balance at handover.</p>
    @endif

    @if ($voucher['security_deposit'] !== null)
        <div class="nb">
            <table>
                <tr>
                    <td><span class="label">Security deposit</span></td>
                    <td style="text-align: right; font-weight: bold; font-size: 12px;">{{ $voucher['security_deposit'] }}</td>
                </tr>
            </table>
            <div class="title" style="margin-top: 6px;">NB</div>
            <p>Security deposit is required upon vehicle handover. The deposit is fully refundable upon return of the rental unit, provided it is returned in the same condition as received, subject to normal wear and tear.</p>
        </div>
    @endif

    <h2>Our office</h2>
    <table class="office">
        <tr>
            <td style="width: 55%;">
                <div class="value">{{ $company->company_name }}</div>
                @if ($company->address)<div>{{ $company->address }}</div>@endif
                @if ($map)<div class="muted">{{ $map }}</div>@endif
            </td>
            <td>
                @if ($company->phones !== [])<div>{{ implode(' · ', $company->phones) }}</div>@endif
                @if ($company->email)<div>{{ $company->email }}</div>@endif
                @foreach ($company->opening_hours as $row)
                    <div class="muted">{{ $row['days'] }}: {{ $row['hours'] }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $company->legal_name ?: $company->company_name }}
    </div>
</body>
</html>
