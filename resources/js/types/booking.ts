import type { MediaCollectionLimits, MediaItem } from './media';
import type { CarCard, Money, Option } from './index';

// Online booking (M8). Mirrors Public\BookingController and Public\BookingResource.

export type HandoverMode = 'office' | 'address';

export type BookingZone = {
    id: number;
    name: string;
    description: string | null;
    // Null: quoted by staff and added to the balance at handover (D33).
    fee: Money | null;
};

export type BookingExtra = {
    code: string;
    name: string;
    description: string | null;
    amount: Money | null;
    basis: string;
    basis_label: string;
    payable_at: string;
};

export type TripOptions = {
    zones: BookingZone[];
    extras: BookingExtra[];
    office: { address: string | null };
};

export type BookingDriver = {
    first_name: string;
    second_name: string | null;
    surname: string;
    dob: string;
    email: string;
    phone: string;
    nationality: string;
    is_kenyan_resident: boolean;
    id_type: string;
    // Saved numbers never come back; only their last characters.
    id_number_hint: string;
    kra_pin_hint: string | null;
    licence_number_hint: string;
    licence_expiry: string;
    licence_years_declared: number;
};

export type Booking = {
    reference: string | null;
    status: string;
    status_label: string;
    current_step: 'trip' | 'driver' | 'review';
    car: CarCard;
    pickup_at: string;
    return_at: string;
    pickup_mode: HandoverMode;
    return_mode: HandoverMode;
    delivery_zone_id: number | null;
    delivery_zone?: string | null;
    delivery_address: string | null;
    delivery_landmark: string | null;
    delivery_lat: string | null;
    delivery_lng: string | null;
    delivery_contact_phone: string | null;
    return_address: string | null;
    return_landmark: string | null;
    return_lat: string | null;
    return_lng: string | null;
    return_contact_phone: string | null;
    extras: string[];
    flight_number: string | null;
    submitted_at: string | null;
    expires_at: string | null;
    driver?: BookingDriver | null;
    // Drafts only: the attached file per document.
    documents?: Record<string, MediaItem | null>;
};

export type DocumentOptions = {
    id_types: Option[];
    // [id_type][resident | non_resident]
    requirements: Record<
        string,
        Record<
            'resident' | 'non_resident',
            { documents: string[]; kra_pin: 'required' | 'optional' | 'hidden' }
        >
    >;
    documents: {
        value: string;
        label: string;
        limits: MediaCollectionLimits;
    }[];
};

// Public\QuoteResource, and the status page's snapshot in the same shape.
export type PublicQuote = {
    days: number;
    lines: {
        code: string;
        label: string;
        quantity: number;
        unit: Money | null;
        amount: Money | null;
        counted: boolean;
        note: string | null;
    }[];
    total: Money;
    reservation: Money;
    balance: Money;
    deposit: Money | null;
    mileage_km_per_day: number | null;
    requires_staff_quote: boolean;
    display: Record<
        string,
        {
            total: Money;
            reservation: Money;
            balance: Money;
            deposit: Money | null;
        }
    >;
};

export type CancellationTier = {
    hours_before_pickup: number;
    refund_percent_bp: number;
    label: string | null;
};
