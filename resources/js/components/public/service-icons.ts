import type { LucideIcon } from 'lucide-react';
import {
    Binoculars,
    BriefcaseBusiness,
    CarFront,
    KeyRound,
    MapPinCheck,
    PlaneLanding,
    UserRoundCheck,
} from 'lucide-react';

// The seeded services' marks; a service staff add later falls back to the car.
const SERVICE_ICONS: Record<string, LucideIcon> = {
    'self-drive-car-rental': KeyRound,
    'chauffeur-driven': UserRoundCheck,
    'airport-transfers': PlaneLanding,
    safaris: Binoculars,
    'corporate-and-events': BriefcaseBusiness,
    'delivery-and-collection': MapPinCheck,
};

export function serviceIcon(slug: string): LucideIcon {
    return SERVICE_ICONS[slug] ?? CarFront;
}
