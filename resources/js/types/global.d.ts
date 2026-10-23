import type { SharedCurrency } from '@/lib/money';
import type { AdminAlerts } from '@/types/admin';
import type { Auth } from '@/types/auth';
import type { SharedSite } from '@/types/public';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            // Admin routes only (M10B).
            alerts?: AdminAlerts | null;
            // Public routes only (ShareCurrency, ShareSite).
            currency?: SharedCurrency;
            site?: SharedSite;
            [key: string]: unknown;
        };
    }
}
