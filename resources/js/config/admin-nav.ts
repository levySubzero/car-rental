import {
    CalendarCheck,
    CalendarRange,
    ArrowLeftRight,
    Banknote,
    Calculator,
    CalendarX,
    CarFront,
    Coins,
    History,
    Inbox,
    LayoutGrid,
    MessageSquare,
    MapPin,
    Receipt,
    PanelsTopLeft,
    Shapes,
    Settings2,
    ShieldCheck,
    Tags,
    Users,
    Wallet,
} from 'lucide-react';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import CancellationTierController from '@/actions/App/Http/Controllers/Admin/CancellationTierController';
import AvailabilityController from '@/actions/App/Http/Controllers/Admin/AvailabilityController';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingRequestController from '@/actions/App/Http/Controllers/Admin/BookingRequestController';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import CarTypeController from '@/actions/App/Http/Controllers/Admin/CarTypeController';
import ChargeController from '@/actions/App/Http/Controllers/Admin/ChargeController';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import ContactMessageController from '@/actions/App/Http/Controllers/Admin/ContactMessageController';
import CurrencyController from '@/actions/App/Http/Controllers/Admin/CurrencyController';
import DeliveryZoneController from '@/actions/App/Http/Controllers/Admin/DeliveryZoneController';
import DepositBandController from '@/actions/App/Http/Controllers/Admin/DepositBandController';
import FxRateController from '@/actions/App/Http/Controllers/Admin/FxRateController';
import PaymentSubmissionController from '@/actions/App/Http/Controllers/Admin/PaymentSubmissionController';
import QuoteSandboxController from '@/actions/App/Http/Controllers/Admin/QuoteSandboxController';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { permissionsFor } from '@/lib/can';
import { dashboard } from '@/routes/admin';
import type { NavGroup } from '@/types';

/**
 * Admin sidebar. Each module adds its entry here with the permission that unlocks it.
 * Groups with no visible items are hidden.
 */
export const adminNav: NavGroup[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
                exact: true,
            },
        ],
    },
    {
        // Day-to-day desk work; bookings join in M11.
        title: 'Operations',
        items: [
            {
                title: 'Bookings',
                href: BookingController.index(),
                icon: CalendarCheck,
                permission: 'bookings.view',
            },
            {
                title: 'Availability',
                href: AvailabilityController.index(),
                icon: CalendarRange,
                permission: ['bookings.view', 'availability.manage'],
            },
            {
                title: 'Payments',
                href: PaymentSubmissionController.index(),
                icon: Wallet,
                permission: 'payments.view',
            },
            {
                title: 'Requests',
                href: BookingRequestController.index(),
                icon: MessageSquare,
                permission: 'booking-requests.view',
            },
            {
                title: 'Messages',
                href: ContactMessageController.index(),
                icon: Inbox,
                permission: 'contact-messages.view',
            },
        ],
    },
    {
        title: 'Catalog',
        items: [
            {
                title: 'Cars',
                href: CarController.index(),
                icon: CarFront,
                permission: 'cars.view',
            },
            {
                title: 'Brands',
                href: BrandController.index(),
                icon: Tags,
                permission: 'brands.view',
            },
            {
                title: 'Car types',
                href: CarTypeController.index(),
                icon: Shapes,
                permission: 'car-types.view',
            },
        ],
    },
    {
        // Super admin by default (PRD §3): pricing rules change what renters pay.
        title: 'Pricing',
        items: [
            {
                title: 'Quote sandbox',
                href: QuoteSandboxController(),
                icon: Calculator,
                permission: 'quotes.sandbox',
            },
            {
                title: 'Charges',
                href: ChargeController.index(),
                icon: Receipt,
                permission: 'charges.view',
            },
            {
                title: 'Delivery zones',
                href: DeliveryZoneController.index(),
                icon: MapPin,
                permission: 'delivery-zones.view',
            },
            {
                title: 'Deposit bands',
                href: DepositBandController.index(),
                icon: Coins,
                permission: 'deposit-bands.view',
            },
            {
                title: 'Cancellation tiers',
                href: CancellationTierController.index(),
                icon: CalendarX,
                permission: 'cancellation-tiers.view',
            },
            {
                title: 'Exchange rates',
                href: FxRateController.index(),
                icon: ArrowLeftRight,
                permission: 'fx-rates.view',
            },
            {
                title: 'Currencies',
                href: CurrencyController.index(),
                icon: Banknote,
                permission: 'currencies.view',
            },
        ],
    },
    {
        title: 'System',
        items: [
            {
                title: 'Users',
                href: UserController.index(),
                icon: Users,
                permission: 'users.view',
            },
            {
                title: 'Roles',
                href: RoleController.index(),
                icon: ShieldCheck,
                permission: 'roles.view',
            },
            {
                // Pages, services, policies and payment methods, as tabs.
                title: 'Content',
                href: ContentController(),
                icon: PanelsTopLeft,
                permission: [
                    'pages.view',
                    'services.view',
                    'policies.view',
                    'payment-methods.view',
                ],
            },
            {
                title: 'Settings',
                href: SettingsController.index(),
                icon: Settings2,
                permission: permissionsFor('settings'),
            },
            {
                title: 'Activity log',
                href: ActivityLogController.index(),
                icon: History,
                permission: 'activity.view',
            },
        ],
    },
];
