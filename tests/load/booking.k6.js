/*
 * Load test for booking submit and the upload path (M12.12). Run against staging, never
 * production:
 *
 *   k6 run -e BASE_URL=https://staging.example -e CAR=mazda-demio -e ZONE_ID=1 tests/load/booking.k6.js
 *   k6 run -e SCENARIO=uploads ... tests/load/booking.k6.js      (uploads only)
 *
 * Before running, on staging:
 * - Turnstile on its test secret (any token passes outside production).
 * - The per-IP limiters would stop one machine after a few bookings. Each virtual user sends
 *   its own X-Forwarded-For, so hit the origin directly (not through Cloudflare) and add the
 *   load machine's address to TRUSTED_PROXIES for the run. Remove it afterwards.
 * - Each run leaves bookings holding the car's dates; `bookings:expire` releases them, or
 *   use dates far ahead (PICKUP_DAYS) and clean up with a database snapshot.
 *
 * Pass: p95 under 1.5 s, no 5xx, and afterwards no overlapping booked or held blocks for the
 * car in car_availability_blocks (one submit wins each date range; the rest get "taken").
 */
import { check, sleep } from 'k6';
import http from 'k6/http';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const CAR = __ENV.CAR || 'mazda-demio';
const ZONE_ID = Number(__ENV.ZONE_ID || 1);
const PICKUP_DAYS = Number(__ENV.PICKUP_DAYS || 300);

// A one-page PDF, enough for every KYC collection.
const PDF = `%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj
trailer << /Root 1 0 R >>
%%EOF
`;

export const options = {
    scenarios:
        __ENV.SCENARIO === 'uploads'
            ? {
                  uploads: {
                      executor: 'constant-vus',
                      vus: 20,
                      duration: '5m',
                      exec: 'uploads',
                  },
              }
            : {
                  booking: {
                      executor: 'ramping-vus',
                      stages: [
                          { duration: '1m', target: 20 },
                          { duration: '3m', target: 20 },
                          { duration: '1m', target: 0 },
                      ],
                      exec: 'booking',
                  },
              },
    thresholds: {
        http_req_duration: ['p(95)<1500'],
        'http_req_failed{status:5xx}': ['rate==0'],
        checks: ['rate>0.95'],
    },
};

function fakeIp() {
    return `10.${__VU % 250}.${Math.floor(Math.random() * 250)}.${(__ITER % 250) + 1}`;
}

function session() {
    const jar = http.cookieJar();
    const ip = fakeIp();
    http.get(`${BASE}/cars/${CAR}/book`, {
        headers: { 'X-Forwarded-For': ip },
    });
    const xsrf = decodeURIComponent(
        jar.cookiesForURL(BASE)['XSRF-TOKEN']?.[0] || '',
    );

    return {
        ip,
        headers: (extra = {}) => ({
            'X-XSRF-TOKEN': xsrf,
            'X-Forwarded-For': ip,
            Accept: 'text/html, application/json',
            ...extra,
        }),
    };
}

function trip(offsetDays) {
    const day = new Date(Date.now() + offsetDays * 86_400_000)
        .toISOString()
        .slice(0, 10);
    const back = new Date(Date.now() + (offsetDays + 2) * 86_400_000)
        .toISOString()
        .slice(0, 10);

    return {
        pickup_at: `${day}T10:00:00+03:00`,
        return_at: `${back}T10:00:00+03:00`,
        pickup_mode: 'address',
        return_mode: 'office',
        delivery_zone_id: ZONE_ID,
        delivery_address: 'Riverside Drive, Westlands',
        delivery_contact_phone: '+254 700 000 001',
        extras: [],
        'cf-turnstile-response': 'load-test',
    };
}

function upload(s, token, collection) {
    const res = http.post(
        `${BASE}/media`,
        { collection, file: http.file(PDF, 'licence.pdf', 'application/pdf') },
        {
            headers: s.headers({
                'X-Booking-Token': token,
                Accept: 'application/json',
            }),
        },
    );
    check(res, { 'upload 201': (r) => r.status === 201 });

    return res.json('uuid');
}

function start(s, offsetDays) {
    const res = http.post(
        `${BASE}/cars/${CAR}/book`,
        JSON.stringify(trip(offsetDays)),
        {
            headers: s.headers({ 'Content-Type': 'application/json' }),
            redirects: 0,
        },
    );
    const location = res.headers.Location || '';
    check(res, { 'draft started': () => location.includes('/bookings/') });

    return location.split('/bookings/')[1]?.split(/[/?]/)[0];
}

export function booking() {
    const s = session();
    // Spread VUs over a few date ranges, so some submits race for the same dates.
    const token = start(s, PICKUP_DAYS + (__VU % 5) * 3);

    if (!token) {
        return;
    }

    const documents = {};
    for (const doc of ['id_front', 'id_back', 'kra_certificate', 'licence']) {
        documents[doc] = upload(s, token, `booking.${doc}`);
    }

    const driver = http.put(
        `${BASE}/bookings/${token}/driver`,
        JSON.stringify({
            first_name: 'Load',
            surname: `Test ${__VU}`,
            dob: '1990-01-01',
            email: `load+${__VU}-${__ITER}@example.test`,
            phone: '+254 700 000 002',
            nationality: 'Kenyan',
            is_kenyan_resident: true,
            id_type: 'national_id',
            id_number: `${10_000_000 + __VU * 1000 + __ITER}`,
            kra_pin: 'A123456789Z',
            licence_number: 'DL-1234567',
            licence_expiry: '2030-01-01',
            licence_years_declared: 8,
            documents,
        }),
        {
            headers: s.headers({ 'Content-Type': 'application/json' }),
            redirects: 0,
        },
    );
    check(driver, { 'driver saved': (r) => r.status < 400 });

    const submit = http.post(
        `${BASE}/bookings/${token}/submit`,
        JSON.stringify({ accept_terms: true, accept_privacy: true }),
        {
            headers: s.headers({ 'Content-Type': 'application/json' }),
            redirects: 0,
        },
    );
    check(submit, { 'submit answered': (r) => r.status < 500 });

    sleep(1);
}

export function uploads() {
    const s = session();
    const token = start(s, PICKUP_DAYS + 60 + (__VU % 20) * 3);

    if (token) {
        upload(s, token, 'booking.licence');
    }

    sleep(1);
}
