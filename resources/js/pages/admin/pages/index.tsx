import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import { ContentTabs } from '@/components/admin/content/content-tabs';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';
import type { AdminContentPage } from '@/types';

export default function PagesIndex({ pages }: { pages: AdminContentPage[] }) {
    return (
        <>
            <Head title="Pages" />
            <AdminPage>
                <PageHeader
                    title="Content"
                    description="The copy on the site's fixed pages. Layout and design are set by the website; here you edit the words and images."
                />
                <ContentTabs />
                <ul className="grid max-w-3xl divide-y rounded-md border">
                    {pages.map((page) => (
                        <li key={page.slug}>
                            <Link
                                href={PageController.edit(page.slug)}
                                className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent"
                            >
                                <div className="grid min-w-0 flex-1 gap-0.5">
                                    <span className="font-medium">
                                        {page.title}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {page.updated_by
                                            ? `Updated ${formatDateTime(page.updated_at)} by ${page.updated_by}`
                                            : 'Not edited yet'}
                                    </span>
                                </div>
                                {page.missing.length > 0 ? (
                                    <Badge variant="outline">
                                        Needs content
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">Complete</Badge>
                                )}
                                <ChevronRight className="size-4 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </AdminPage>
        </>
    );
}

PagesIndex.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Pages', href: PageController.index() },
    ],
};
