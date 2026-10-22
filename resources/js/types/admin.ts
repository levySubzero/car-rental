// Shapes of the admin API Resources (app/Http/Resources/Admin). Hand-maintained (TECHNICAL_PLAN §3).
import type { MediaGuidance, MediaItem } from './media';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    email_verified: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    created_at: string | null;
    roles?: string[];
    permissions?: string[];
};

export type AdminRole = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    users_count?: number;
    permissions_count?: number;
    permissions?: string[];
};

// Module name → permission names, e.g. { users: ['users.view', ...] }
export type PermissionOptions = Record<string, string[]>;

export type ActivityEntry = {
    id: number;
    log: string | null;
    event: string | null;
    description: string;
    subject: { type: string; id: number | string; label: string | null } | null;
    causer: { id: number | string; label: string | null } | null;
    changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    };
    properties: Record<string, unknown>;
    created_at: string | null;
};

export type SettingsTab = {
    key: string;
    label: string;
};

// Catalog (M4)

// Mirrors App\Support\Money::jsonSerialize().
export type Money = {
    minor: number;
    currency: string;
    major: string;
    display: string;
};

export type Option = { value: string; label: string };

export type AdminBrand = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    show_in_marquee: boolean;
    is_active: boolean;
    cars_count?: number;
    logo?: MediaItem | null;
    updated_at: string | null;
};

export type AdminCarType = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    cars_count?: number;
};

export type AdminCarBlackout = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string;
    reason_label: string;
    notes: string | null;
    created_by?: string | null;
};

export type AdminCar = {
    id: number;
    name: string;
    slug: string;
    brand_id: number;
    car_type_id: number;
    brand?: { id: number; name: string };
    car_type?: { id: number; name: string };
    model_year: number | null;
    engine_type: string | null;
    // Litres with one decimal, e.g. "2.5".
    engine_capacity_litres: string | null;
    fuel_type: string;
    transmission: string;
    colour: string | null;
    doors: number;
    seats: number;
    daily_rate: Money;
    // Only present for staff with cars.view-value.
    car_value?: Money | null;
    mileage_limit_km: number | null;
    damage_excess: Money | null;
    is_featured: boolean;
    is_available: boolean;
    is_published: boolean;
    published_at: string | null;
    description: string | null;
    // Informal extras (D20): snake_case key → text.
    metadata: Record<string, string>;
    cover?: MediaItem | null;
    gallery?: MediaItem[];
    blackouts?: AdminCarBlackout[];
    updated_at: string | null;
};

// Content (M5)

export type AdminService = {
    id: number;
    title: string;
    slug: string;
    summary: string;
    // Markdown (D23).
    body: string | null;
    cta_type: 'book' | 'whatsapp' | 'link';
    cta_label: string;
    cta_value: string | null;
    sort_order: number;
    is_active: boolean;
    image?: MediaItem | null;
    updated_at: string | null;
};

// Mirrors App\Support\Content\ContentField::toArray() (config/pages.php).
export type ContentFieldDefinition = {
    key: string;
    type: 'text' | 'markdown' | 'image' | 'list';
    label: string;
    section: string | null;
    help: string | null;
    required: boolean;
    max: number;
    multiline: boolean;
    items: number;
    fields: ContentFieldDefinition[];
    // Image fields only: replaces the page.image collection's guidance.
    guidance?: MediaGuidance | null;
};

export type ContentListItem = Record<string, string | null>;

export type ContentValue = string | null | ContentListItem[];

// A preset page (D22). `fields` and `images` are only sent to the editor.
export type AdminContentPage = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    fields?: ContentFieldDefinition[];
    content: Record<string, ContentValue>;
    // Labels of required fields still empty.
    missing: string[];
    meta_title: string | null;
    meta_description: string | null;
    images?: Record<string, MediaItem>;
    updated_by?: string | null;
    updated_at: string | null;
};

export type AdminPolicyVersion = {
    id: number;
    version: number;
    // Markdown (D23).
    body: string;
    has_confirm: boolean;
    published_at: string | null;
    // "Seed pack" for versions the seeder wrote.
    created_by?: string;
    updated_at: string | null;
};

export type AdminPolicy = {
    id: number;
    title: string;
    slug: string;
    group: string;
    group_label: string;
    summary: string | null;
    show_on_home: boolean;
    sort_order: number;
    published?: AdminPolicyVersion | null;
    draft?: AdminPolicyVersion | null;
    // Newest first.
    versions?: AdminPolicyVersion[];
    updated_at: string | null;
};

export type PolicyBundleSummary = {
    version: number;
    published_at: string | null;
    policies: number;
};

export type AdminPaymentMethod = {
    id: number;
    code: 'mpesa' | 'bank' | 'card' | 'cash';
    name: string;
    // Markdown (D23).
    instructions: string;
    requires_transaction_message: boolean;
    requires_proof: boolean;
    is_active: boolean;
    sort_order: number;
    // Paybill, till, bank details, card link (D20).
    metadata: Record<string, string>;
    has_todo: boolean;
    updated_at: string | null;
};

export type ContactMessageStatus = 'new' | 'in_progress' | 'resolved' | 'spam';

export type AdminContactMessage = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    message: string;
    preferred_dates: string | null;
    status: ContactMessageStatus;
    status_label: string;
    car?: { name: string; slug: string } | null;
    handled_by?: string | null;
    handled_at: string | null;
    created_at: string | null;
};

// Mirrors App\Services\Cms\SetupChecklistService.
export type SetupChecklistItem = {
    key: string;
    area: string;
    label: string;
    detail: string;
    url: string;
};

// Pricing (M6)

export type AdminCurrency = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    // Display decimals only; amounts are stored with 2 (D27).
    decimals: number;
    is_active: boolean;
    is_base: boolean;
    sort_order: number;
};

// Mirrors App\Services\Pricing\Fx\ResolvedRate::toArray(). Rates are decimal strings.
export type ResolvedFxRate = {
    code: string;
    market: string;
    margin: string;
    effective: string;
    source: 'api' | 'manual';
    fetched_at: string;
    stale: boolean;
    override_until: string | null;
};

export type AdminFxRate = {
    id: number;
    currency_code: string;
    market_rate: string;
    source: 'api' | 'manual';
    source_label: string;
    provider: string | null;
    fetched_at: string;
    override_until: string | null;
    override_by?: string | null;
    is_live_override: boolean;
};

export type FxRefreshOutcome = {
    at: string;
    status: 'refreshed' | 'skipped' | 'failed';
    message: string;
    stored: Record<string, string>;
    rejected: Record<
        string,
        { previous: string; fetched: string; change: string }
    >;
};

export type AdminCharge = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    basis: 'per_day' | 'per_booking' | 'per_km' | 'info_only';
    basis_label: string;
    // Null for info-only and per-km charges quoted on request.
    amount: Money | null;
    currency_code: string;
    tax_inclusive: boolean;
    payable_at: 'reservation' | 'handover' | 'counter';
    payable_at_label: string;
    // When it goes on a quote (D32).
    applies: 'extra' | 'always' | 'listed';
    applies_label: string;
    is_active: boolean;
    sort_order: number;
};

export type AdminDeliveryZone = {
    id: number;
    name: string;
    description: string | null;
    // Null means staff quote the fee.
    fee: Money | null;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
};

export type AdminDepositBand = {
    id: number;
    min_age: number;
    max_age: number;
    // Basis points of the car value: 200 = 2%.
    percent_bp: number;
    label: string | null;
    is_active: boolean;
};

export type AdminCancellationTier = {
    id: number;
    hours_before_pickup: number;
    refund_percent_bp: number;
    label: string | null;
};

export type QuoteLine = {
    kind: 'rental' | 'charge' | 'delivery';
    code: string;
    label: string;
    quantity: number;
    // In the charge's own currency.
    unit: Money | null;
    // In KES; null when quoted on request or by staff.
    amount: Money | null;
    payable_at: string;
    counted: boolean;
    note: string | null;
};

// Mirrors App\Services\Pricing\Quote\Quote::toArray() (Admin\QuoteResource).
export type AdminQuote = {
    pickup_at: string;
    return_at: string;
    days: number;
    lines: QuoteLine[];
    rental: Money;
    total: Money;
    reservation: Money;
    balance: Money;
    // Null without a date of birth, or for staff without cars.view-value.
    deposit: Money | null;
    deposit_band: {
        min_age: number;
        max_age: number;
        percent_bp: number;
    } | null;
    deposit_hidden?: boolean;
    driver_age: number | null;
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
    fx: { base: string; rates: Record<string, ResolvedFxRate> };
    settings: Record<string, string | number | null>;
    settings_hash: string;
};
