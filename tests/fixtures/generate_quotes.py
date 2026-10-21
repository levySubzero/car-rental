"""Independent reference implementation of the quote rules (TECHNICAL_PLAN §10, D27–D30).

Writes tests/fixtures/quotes.json. The PHP QuoteCalculator must reproduce every expectation.
"""
import json, sys
from datetime import datetime, date, timedelta, timezone
from decimal import Decimal, ROUND_HALF_UP, getcontext
from zoneinfo import ZoneInfo

getcontext().prec = 50
TZ = ZoneInfo('Africa/Nairobi')

DEFAULT_SETTINGS = {
    'reservation_percent_bp': 2500, 'reservation_rounding': 'ceil_major', 'day_counting': 'calendar_inclusive',
    'daytime_window_start': '07:00', 'daytime_window_end': '19:00', 'rolling_grace_minutes': 60,
    'default_mileage_km': 300, 'unlimited_mileage_after_days': None,
}
DEFAULT_FX = {'USD': '129', 'GBP': '163.5', 'EUR': '140.25', 'AED': '35.12'}
DEFAULT_MARGIN = '3'
CHARGES = [
    {'code': 'chauffeur', 'name': 'Chauffeur', 'basis': 'per_day', 'amount': '50', 'currency': 'USD', 'payable_at': 'reservation', 'applies': 'extra', 'is_active': True},
    {'code': 'additional_driver', 'name': 'Additional driver', 'basis': 'per_day', 'amount': '50', 'currency': 'USD', 'payable_at': 'counter', 'applies': 'extra', 'is_active': True},
    {'code': 'fuel_service', 'name': 'Fuel service charge', 'basis': 'per_booking', 'amount': '20', 'currency': 'USD', 'payable_at': 'counter', 'applies': 'extra', 'is_active': True},
    {'code': 'valeting', 'name': 'Valeting', 'basis': 'per_booking', 'amount': '1500', 'currency': 'KES', 'payable_at': 'handover', 'applies': 'extra', 'is_active': True},
    {'code': 'full_protection', 'name': 'Full protection', 'basis': 'info_only', 'amount': None, 'currency': 'KES', 'payable_at': 'counter', 'applies': 'extra', 'is_active': True},
    {'code': 'excess_km', 'name': 'Excess kilometres', 'basis': 'per_km', 'amount': None, 'currency': 'KES', 'payable_at': 'counter', 'applies': 'extra', 'is_active': True},
]
ZONES = {
    'Nairobi': {'name': 'Nairobi', 'fee': '2000'},
    'Outside Nairobi': {'name': 'Outside Nairobi', 'fee': None},
    'JKIA': {'name': 'JKIA', 'fee': '2500.50'},
}
BANDS = [{'min_age': 23, 'max_age': 23, 'percent_bp': 300}, {'min_age': 24, 'max_age': 70, 'percent_bp': 200}]
PRADO = {'name': 'Toyota Prado TX', 'daily_rate': '15000', 'value': '8000000', 'mileage_limit_km': None}


def minor(major):
    return None if major is None else int((Decimal(major) * 100).to_integral_value(ROUND_HALF_UP))


def major(m):
    if m is None:
        return None
    sign = '-' if m < 0 else ''
    m = abs(m)
    return f"{sign}{m // 100}.{m % 100:02d}"


def half_up(x):
    return int(Decimal(x).quantize(Decimal(1), rounding=ROUND_HALF_UP))


def parse(s):
    return datetime.fromisoformat(s)


def days_for(pickup, ret, s):
    strategy = s['day_counting']
    if strategy == 'calendar_inclusive':
        return max(1, (ret.astimezone(TZ).date() - pickup.astimezone(TZ).date()).days + 1)
    if strategy == 'rolling_24h':
        minutes = int((ret - pickup).total_seconds()) // 60
        return max(1, minutes // 1440 + (1 if minutes % 1440 > s['rolling_grace_minutes'] else 0))
    if strategy == 'daytime_blocks':
        d, last, blocks = pickup.astimezone(TZ).date(), ret.astimezone(TZ).date(), 0
        sh, sm = map(int, s['daytime_window_start'].split(':'))
        eh, em = map(int, s['daytime_window_end'].split(':'))
        while d <= last:
            start = datetime(d.year, d.month, d.day, sh, sm, tzinfo=TZ)
            end = datetime(d.year, d.month, d.day, eh, em, tzinfo=TZ)
            if start < ret and end > pickup:
                blocks += 1
            d += timedelta(days=1)
        return max(1, blocks)
    raise ValueError(strategy)


def percent(total, bp, mode):
    n = total * bp
    if mode == 'floor':
        return n // 10000
    if mode == 'round':
        return (n * 2 + 10000) // 20000
    if mode == 'ceil_major':
        return ((n + 10000 * 100 - 1) // (10000 * 100)) * 100
    raise ValueError(mode)


def age_on(dob, day):
    return day.year - dob.year - (1 if (day.month, day.day) < (dob.month, dob.day) else 0)


class QuoteError(Exception):
    pass


def quote(case):
    s = {**DEFAULT_SETTINGS, **case.get('settings', {})}
    fx = case.get('fx', DEFAULT_FX)
    margin = Decimal(case.get('margin', DEFAULT_MARGIN))
    charges = case.get('charges', CHARGES) + case.get('extra_charges', [])
    car = case.get('car', PRADO)
    pickup, ret = parse(case['pickup']), parse(case['return'])
    if not ret > pickup:
        raise QuoteError('return_before_pickup')

    days = days_for(pickup, ret, s)
    daily = minor(car['daily_rate'])
    lines = [{'code': 'rental', 'amount': major(daily * days), 'counted': True}]
    total = daily * days

    for c in charges:
        applies = c['is_active'] and {'extra': c['code'] in case.get('extras', []), 'always': True, 'listed': False}[c['applies']]
        if not applies:
            continue
        quotable = c['basis'] in ('per_day', 'per_booking') and c['amount'] is not None
        if not quotable:
            lines.append({'code': c['code'], 'amount': None, 'counted': False})
            continue
        qty = days if c['basis'] == 'per_day' else 1
        source = minor(c['amount']) * qty
        if c['currency'] == 'KES':
            kes = source
        else:
            if c['currency'] not in fx:
                raise QuoteError('missing_fx')
            kes = half_up(Decimal(source) * Decimal(fx[c['currency']]))
        counted = c['payable_at'] != 'counter'
        lines.append({'code': c['code'], 'amount': major(kes), 'counted': counted})
        if counted:
            total += kes

    staff = False
    if case.get('zone'):
        z = ZONES[case['zone']]
        fee = minor(z['fee'])
        staff = fee is None
        lines.append({'code': 'delivery', 'amount': major(fee), 'counted': fee is not None})
        if fee is not None:
            total += fee

    reservation = percent(total, s['reservation_percent_bp'], s['reservation_rounding'])
    balance = total - reservation

    age = deposit = band = None
    if case.get('dob'):
        dob = date.fromisoformat(case['dob'])
        age = age_on(dob, pickup.astimezone(TZ).date())
        match = [b for b in BANDS if b['min_age'] <= age <= b['max_age']]
        if not match:
            raise QuoteError('driver_age')
        band = match[0]['percent_bp']
        if car.get('value') is not None:
            deposit = percent(minor(car['value']), band, 'round')

    unlimited = s['unlimited_mileage_after_days'] is not None and days >= s['unlimited_mileage_after_days']
    mileage = None if unlimited else (car.get('mileage_limit_km') or s['default_mileage_km'])

    display = {}
    for code, rate in fx.items():
        effective = Decimal(rate) - margin
        conv = lambda m: None if m is None else major(half_up(Decimal(m) / effective))
        display[code] = {'total': conv(total), 'reservation': conv(reservation), 'balance': conv(balance), 'deposit': conv(deposit)}

    return {
        'days': days, 'total': major(total), 'reservation': major(reservation), 'balance': major(balance),
        'deposit': major(deposit), 'deposit_percent_bp': band, 'driver_age': age, 'mileage_km_per_day': mileage,
        'requires_staff_quote': staff, 'lines': lines, 'display': display,
    }


def at(day, time, offset='+03:00'):
    return f"2026-11-{day:02d}T{time}:00{offset}"


C = []
def case(name, **kw):
    C.append({'name': name, **kw})

# The PRD §5 worked example.
case('PRD worked example: Prado, 3 days, chauffeur and Nairobi delivery, driver 35', pickup=at(2, '10:00'), return_=at(4, '10:00'), extras=['chauffeur'], zone='Nairobi', dob='1991-03-15')

# Calendar-inclusive days (the client's rule).
case('calendar: same-day rental is one day', pickup=at(2, '10:00'), return_=at(2, '18:00'))
case('calendar: overnight across midnight is two days', pickup=at(2, '23:00'), return_=at(3, '01:00'))
case('calendar: Monday 10:00 to Thursday 09:00 is four days', pickup=at(2, '10:00'), return_=at(5, '09:00'))
case('calendar: UTC times are counted on Nairobi dates', pickup='2026-11-02T20:30:00+00:00', return_='2026-11-02T22:00:00+00:00')
case('calendar: thirty days', pickup=at(1, '09:00'), return_=at(30, '09:00'))

# Rolling 24-hour periods.
R = {'day_counting': 'rolling_24h'}
case('rolling: exactly 24 hours is one day', settings=R, pickup=at(2, '10:00'), return_=at(3, '10:00'))
case('rolling: 72h30 within the 60-minute grace is three days', settings=R, pickup=at(2, '10:00'), return_=at(5, '10:30'))
case('rolling: 73h01 past the grace is four days', settings=R, pickup=at(2, '10:00'), return_=at(5, '11:01'))
case('rolling: 73h00 exactly on the grace is three days', settings=R, pickup=at(2, '10:00'), return_=at(5, '11:00'))
case('rolling: no grace, 24h01 is two days', settings={**R, 'rolling_grace_minutes': 0}, pickup=at(2, '10:00'), return_=at(3, '10:01'))
case('rolling: thirty minutes is still one day', settings=R, pickup=at(2, '10:00'), return_=at(2, '10:30'))

# Daytime blocks.
D = {'day_counting': 'daytime_blocks'}
case('daytime: one full window is one day', settings=D, pickup=at(2, '07:00'), return_=at(2, '19:00'))
case('daytime: night only is the minimum of one day', settings=D, pickup=at(2, '19:00'), return_=at(3, '07:00'))
case('daytime: 18:00 to 08:00 touches two windows', settings=D, pickup=at(2, '18:00'), return_=at(3, '08:00'))
case('daytime: 19:00 Monday to 07:00 Wednesday uses only Tuesday', settings=D, pickup=at(2, '19:00'), return_=at(4, '07:00'))
case('daytime: a custom 08:00–20:00 window', settings={**D, 'daytime_window_start': '08:00', 'daytime_window_end': '20:00'}, pickup=at(2, '19:30'), return_=at(3, '08:30'))
case('daytime: three days 10:00 to 10:00', settings=D, pickup=at(2, '10:00'), return_=at(4, '10:00'))

# Reservation rounding.
CENTS = {'name': 'Odd rate', 'daily_rate': '15000.50', 'value': '5000000', 'mileage_limit_km': None}
case('rounding: ceil to the whole shilling', car=CENTS, pickup=at(2, '10:00'), return_=at(2, '16:00'))
case('rounding: half up to the cent', car=CENTS, settings={'reservation_rounding': 'round'}, pickup=at(2, '10:00'), return_=at(2, '16:00'))
case('rounding: down to the cent', car=CENTS, settings={'reservation_rounding': 'floor'}, pickup=at(2, '10:00'), return_=at(2, '16:00'))
case('rounding: 30% reservation', settings={'reservation_percent_bp': 3000}, pickup=at(2, '10:00'), return_=at(4, '10:00'), extras=['chauffeur'], zone='Nairobi')
case('rounding: 100% reservation leaves no balance', settings={'reservation_percent_bp': 10000}, pickup=at(2, '10:00'), return_=at(3, '10:00'))
case('rounding: ceil when the total has cents from delivery', pickup=at(2, '10:00'), return_=at(3, '10:00'), zone='JKIA')

# Charges.
case('charges: additional driver is paid at the counter, not in the total', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['additional_driver'])
case('charges: fuel service is per booking at the counter', pickup=at(2, '10:00'), return_=at(5, '10:00'), extras=['fuel_service'])
case('charges: a handover charge counts towards the total', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['valeting'])
case('charges: information-only charges are listed without an amount', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['full_protection'])
case('charges: per-km charges are listed, never totalled', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['excess_km'])
case('charges: an always-on charge is on every quote', pickup=at(2, '10:00'), return_=at(3, '10:00'),
     extra_charges=[{'code': 'admin', 'name': 'Admin fee', 'basis': 'per_booking', 'amount': '500', 'currency': 'KES', 'payable_at': 'reservation', 'applies': 'always', 'is_active': True}])
case('charges: a listed-only charge is never on a quote', pickup=at(2, '10:00'), return_=at(3, '10:00'),
     extra_charges=[{'code': 'admin', 'name': 'Admin fee', 'basis': 'per_booking', 'amount': '500', 'currency': 'KES', 'payable_at': 'reservation', 'applies': 'listed', 'is_active': True}])
case('charges: an inactive charge is ignored even when picked', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['one_way'],
     extra_charges=[{'code': 'one_way', 'name': 'One-way', 'basis': 'per_booking', 'amount': '5000', 'currency': 'KES', 'payable_at': 'reservation', 'applies': 'extra', 'is_active': False}])
case('charges: a euro charge converts at the market rate', pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['one_way'],
     extra_charges=[{'code': 'one_way', 'name': 'One-way', 'basis': 'per_booking', 'amount': '30', 'currency': 'EUR', 'payable_at': 'reservation', 'applies': 'extra', 'is_active': True}])
case('charges: a six-place market rate rounds half up to the cent', fx={**DEFAULT_FX, 'USD': '129.45675'}, pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['chauffeur'])
case('charges: everything at once over five days', pickup=at(2, '10:00'), return_=at(6, '10:00'), extras=['chauffeur', 'additional_driver', 'fuel_service', 'valeting', 'full_protection'], zone='Nairobi', dob='1980-01-01')
case('charges: missing rate for a charge currency is an error', fx={'GBP': '163.5'}, pickup=at(2, '10:00'), return_=at(3, '10:00'), extras=['chauffeur'], error='missing_fx')

# Delivery.
case('delivery: a zone without a fee is quoted by staff and left out', pickup=at(2, '10:00'), return_=at(3, '10:00'), zone='Outside Nairobi')

# Deposit bands by age at pickup.
case('age: 22 is below every band', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='2004-01-01', error='driver_age')
case('age: 23 pays 3% of the car value', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='2003-01-01')
case('age: 24 pays 2%', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='2002-01-01')
case('age: 70 pays 2%', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='1956-01-01')
case('age: 71 is above every band', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='1955-01-01', error='driver_age')
case('age: the birthday on the pickup date counts', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='2002-11-02')
case('age: the day before the birthday does not', pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='2002-11-03')
case('age: the pickup date is taken in Nairobi time', pickup='2026-11-02T22:30:00+00:00', return_='2026-11-04T10:00:00+03:00', dob='2002-11-03')
case('age: born on 29 February is a year older on 1 March', pickup='2023-03-01T10:00:00+03:00', return_='2023-03-02T10:00:00+03:00', dob='2000-02-29')
case('age: born on 29 February is not yet older on 28 February', pickup='2023-02-28T10:00:00+03:00', return_='2023-03-01T10:00:00+03:00', dob='2000-02-29', error='driver_age')
case('age: no date of birth means no deposit yet', pickup=at(2, '10:00'), return_=at(3, '10:00'))
case('age: no car value means no deposit amount', car={**PRADO, 'value': None}, pickup=at(2, '10:00'), return_=at(3, '10:00'), dob='1990-06-01')

# Mileage.
case('mileage: the default allowance', pickup=at(2, '10:00'), return_=at(3, '10:00'))
case("mileage: the car's own allowance", car={**PRADO, 'mileage_limit_km': 250}, pickup=at(2, '10:00'), return_=at(3, '10:00'))
case('mileage: unlimited from seven days', settings={'unlimited_mileage_after_days': 7}, pickup=at(2, '10:00'), return_=at(8, '10:00'))
case('mileage: limited at six days', settings={'unlimited_mileage_after_days': 7}, pickup=at(2, '10:00'), return_=at(7, '10:00'))

# Display currencies.
case('display: a currency without a rate is not shown', fx={'USD': '129', 'GBP': '163.5'}, pickup=at(2, '10:00'), return_=at(4, '10:00'))
case('display: a 2.50 margin', margin='2.5', pickup=at(2, '10:00'), return_=at(4, '10:00'), extras=['chauffeur'], zone='Nairobi', dob='1991-03-15')

# Invalid periods.
case('period: return before pickup is an error', pickup=at(3, '10:00'), return_=at(2, '10:00'), error='return_before_pickup')
case('period: return equal to pickup is an error', pickup=at(2, '10:00'), return_=at(2, '10:00'), error='return_before_pickup')

out = []
for c in C:
    c['return'] = c.pop('return_')
    expected_error = c.pop('error', None)
    try:
        result = quote(c)
        assert expected_error is None, f"{c['name']}: expected {expected_error}"
        c['expect'] = result
    except QuoteError as e:
        assert str(e) == expected_error, f"{c['name']}: got {e}, expected {expected_error}"
        c['expect'] = {'error': str(e)}
    out.append(c)

prd = out[0]['expect']
assert (prd['total'], prd['reservation'], prd['balance'], prd['deposit']) == ('66350.00', '16588.00', '49762.00', '160000.00'), prd
assert prd['display']['USD'] == {'total': '526.59', 'reservation': '131.65', 'balance': '394.94', 'deposit': '1269.84'}, prd['display']['USD']

fixture = {
    '_meta': {
        'about': 'Quote engine cases (M6.11). Generated by an independent reference implementation; the PHP QuoteCalculator must match every expectation. Money is in major units as exact strings. Rates are KES per unit; margin is KES.',
        'defaults': {'settings': DEFAULT_SETTINGS, 'timezone': 'Africa/Nairobi', 'fx': DEFAULT_FX, 'margin': DEFAULT_MARGIN, 'car': PRADO},
    },
    'charges': CHARGES,
    'zones': list(ZONES.values()),
    'bands': BANDS,
    'cases': out,
}
json.dump(fixture, open(sys.argv[1], 'w'), indent=2)
print(len(out), 'cases')
