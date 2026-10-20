import { Link } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import PaymentMethodController from '@/actions/App/Http/Controllers/Admin/PaymentMethodController';
import PolicyController from '@/actions/App/Http/Controllers/Admin/PolicyController';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { Permission } from '@/types';

// Same order as App\Http\Controllers\Admin\ContentController::TABS.
const TABS: { title: string; href: string; permission: Permission }[] = [
    {
        title: 'Pages',
        href: PageController.index.url(),
        permission: 'pages.view',
    },
    {
        title: 'Services',
        href: ServiceController.index.url(),
        permission: 'services.view',
    },
    {
        title: 'Policies',
        href: PolicyController.index.url(),
        permission: 'policies.view',
    },
    {
        title: 'Payment methods',
        href: PaymentMethodController.index.url(),
        permission: 'payment-methods.view',
    },
];

/**
 * The tabs of the Content section, styled like the settings tabs. Only tabs the user may
 * open are shown.
 */
export function ContentTabs() {
    const can = useCan();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav
            className="flex gap-1 overflow-x-auto border-b"
            aria-label="Content sections"
        >
            {TABS.filter((tab) => can(tab.permission)).map((tab) => {
                const current = isCurrentOrParentUrl(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        aria-current={current ? 'page' : undefined}
                        className={cn(
                            '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                            current
                                ? 'border-primary font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {tab.title}
                    </Link>
                );
            })}
        </nav>
    );
}
