import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { adminNav } from '@/config/admin-nav';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes/admin';
import type { SetupChecklistItem } from '@/types';

// The operational dashboard (pickups, verifications, stale FX) arrives in M11.10.
export default function Dashboard({
    setupChecklist,
}: {
    setupChecklist: SetupChecklistItem[];
}) {
    const { auth } = usePage().props;
    const can = useCan();
    const shortcuts = adminNav
        .flatMap((group) => group.items)
        .filter((item) => !item.exact && can(item.permission));

    return (
        <>
            <Head title="Dashboard" />
            <AdminPage>
                <PageHeader
                    title={`Welcome, ${auth.user.name}`}
                    description="Manage the Baines Executives website and bookings."
                />
                {setupChecklist.length > 0 && (
                    <SetupChecklist items={setupChecklist} />
                )}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {shortcuts.map((item) => (
                        <Link key={item.title} href={item.href} prefetch>
                            <Card className="h-full transition-colors hover:bg-accent">
                                <CardHeader>
                                    {item.icon && (
                                        <item.icon className="size-5 text-muted-foreground" />
                                    )}
                                    <CardTitle>{item.title}</CardTitle>
                                    <CardDescription>
                                        Open {item.title.toLowerCase()}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </AdminPage>
        </>
    );
}

/**
 * What is still missing before launch (M5.11): only items the viewer can fix are listed.
 */
function SetupChecklist({ items }: { items: SetupChecklistItem[] }) {
    return (
        <Card className="max-w-4xl">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Before launch
                    <Badge variant="secondary">{items.length}</Badge>
                </CardTitle>
                <CardDescription>
                    Details the website still needs. Each item disappears once
                    it is filled in.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="divide-y rounded-md border">
                    {items.map((item) => (
                        <li key={item.key}>
                            <Link
                                href={item.url}
                                className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-accent"
                            >
                                <Badge
                                    variant="outline"
                                    className="hidden w-32 justify-center sm:inline-flex"
                                >
                                    {item.area}
                                </Badge>
                                <div className="grid min-w-0 flex-1">
                                    <span className="truncate text-sm font-medium">
                                        {item.label}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {item.detail}
                                    </span>
                                </div>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
