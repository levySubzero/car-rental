import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PolicyController from '@/actions/App/Http/Controllers/Admin/PolicyController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { PolicyStatus } from '@/components/admin/content/policy-status';
import { ContentTabs } from '@/components/admin/content/content-tabs';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminPolicy, Option, PolicyBundleSummary } from '@/types';

export default function PoliciesIndex({
    policies,
    bundle,
    groupOptions,
}: {
    policies: AdminPolicy[];
    bundle: PolicyBundleSummary | null;
    groupOptions: Option[];
}) {
    const can = useCan();
    const editable = can('policies.update');

    return (
        <>
            <Head title="Policies" />
            <AdminPage>
                <PageHeader
                    title="Content"
                    description={
                        bundle
                            ? `Renters currently accept policy set ${bundle.version} (${bundle.policies} policies, published ${formatDateTime(bundle.published_at)}). Publishing a change creates the next set.`
                            : 'Nothing is published yet. Renters accept the published policies when they book.'
                    }
                    actions={
                        can('policies.create') && (
                            <Button asChild>
                                <Link href={PolicyController.create()}>
                                    <Plus /> New policy
                                </Link>
                            </Button>
                        )
                    }
                />
                <ContentTabs />
                <Tabs defaultValue="sections">
                    <TabsList>
                        <TabsTrigger value="sections">By section</TabsTrigger>
                        <TabsTrigger value="order">Page order</TabsTrigger>
                    </TabsList>
                    <TabsContent value="sections" className="mt-4 grid gap-6">
                        {groupOptions.map((group) => {
                            const rows = policies.filter(
                                (policy) => policy.group === group.value,
                            );

                            return (
                                rows.length > 0 && (
                                    <section
                                        key={group.value}
                                        className="grid gap-2"
                                    >
                                        <h2 className="text-sm font-semibold">
                                            {group.label}
                                        </h2>
                                        <div className="rounded-md border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Policy
                                                        </TableHead>
                                                        <TableHead>
                                                            Status
                                                        </TableHead>
                                                        <TableHead className="w-32">
                                                            On home page
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {rows.map((policy) => (
                                                        <TableRow
                                                            key={policy.id}
                                                        >
                                                            <TableCell>
                                                                <Link
                                                                    href={PolicyController.edit(
                                                                        policy.slug,
                                                                    )}
                                                                    className="font-medium hover:underline"
                                                                >
                                                                    {
                                                                        policy.title
                                                                    }
                                                                </Link>
                                                            </TableCell>
                                                            <TableCell>
                                                                <PolicyStatus
                                                                    policy={
                                                                        policy
                                                                    }
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                <FlagSwitch
                                                                    key={`${policy.id}-${policy.show_on_home}`}
                                                                    url={PolicyController.home.url(
                                                                        policy.slug,
                                                                    )}
                                                                    checked={
                                                                        policy.show_on_home
                                                                    }
                                                                    label={`Show ${policy.title} on the home page`}
                                                                    disabled={
                                                                        !editable
                                                                    }
                                                                />
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </section>
                                )
                            );
                        })}
                        {policies.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No policies yet.
                            </p>
                        )}
                    </TabsContent>
                    <TabsContent value="order" className="mt-4 max-w-2xl">
                        <PolicyOrder policies={policies} editable={editable} />
                    </TabsContent>
                </Tabs>
            </AdminPage>
        </>
    );
}

function PolicyOrder({
    policies,
    editable,
}: {
    policies: AdminPolicy[];
    editable: boolean;
}) {
    const [items, setItems] = useState(policies);

    const row = (policy: AdminPolicy) => (
        <div className="flex items-center gap-3">
            <span className="min-w-0 flex-1 truncate text-sm font-medium">
                {policy.title}
            </span>
            <Badge variant="outline">{policy.group_label}</Badge>
        </div>
    );

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Drag to set the order policies appear in within each section of
                the rental policy page.
            </p>
            {editable ? (
                <SortableList
                    items={items}
                    renderItem={row}
                    onReorder={(ids, reordered) => {
                        setItems(reordered);
                        router.put(
                            PolicyController.reorder.url(),
                            { ids },
                            { preserveScroll: true, preserveState: true },
                        );
                    }}
                />
            ) : (
                <ul className="space-y-2">
                    {items.map((policy) => (
                        <li key={policy.id} className="rounded-md border p-2">
                            {row(policy)}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

PoliciesIndex.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Policies', href: PolicyController.index() },
    ],
};
