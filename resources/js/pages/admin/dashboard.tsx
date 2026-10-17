import { Head, Link, usePage } from '@inertiajs/react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { adminNav } from '@/config/admin-nav';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes/admin';

// The operational dashboard (pickups, verifications, stale FX) arrives in M11.10.
export default function Dashboard() {
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

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
