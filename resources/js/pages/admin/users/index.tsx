import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { UserStatus } from '@/components/admin/users/user-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { formatDateTime, humanize } from '@/lib/format';
import type { AdminUser, Paginated } from '@/types';

type Filters = {
    search?: string;
    role?: string;
    status?: string;
    sort?: string;
    direction?: string;
};

export default function UsersIndex({
    users,
    filters,
    roles,
}: {
    users: Paginated<AdminUser>;
    filters: Filters;
    roles: string[];
}) {
    const can = useCan();
    const apply = useTableQuery(filters);

    const columns: Column<AdminUser>[] = [
        {
            key: 'name',
            header: 'Name',
            sortKey: 'name',
            cell: (user) => (
                <div className="grid">
                    <Link
                        href={UserController.edit(user.id)}
                        className="font-medium hover:underline"
                    >
                        {user.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {user.email}
                    </span>
                </div>
            ),
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (user) => (
                <div className="flex flex-wrap gap-1">
                    {(user.roles ?? []).map((role) => (
                        <Badge key={role} variant="secondary">
                            {humanize(role)}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (user) => <UserStatus user={user} />,
        },
        {
            key: 'last_login_at',
            header: 'Last sign-in',
            sortKey: 'last_login_at',
            className: 'hidden md:table-cell',
            cell: (user) => formatDateTime(user.last_login_at),
        },
    ];

    return (
        <>
            <Head title="Users" />
            <AdminPage>
                <PageHeader
                    title="Users"
                    description="Staff accounts with access to the admin."
                    actions={
                        can('users.create') && (
                            <Button asChild>
                                <Link href={UserController.create()}>
                                    <Plus /> Invite user
                                </Link>
                            </Button>
                        )
                    }
                />
                <DataTable
                    data={users}
                    columns={columns}
                    filters={filters}
                    rowKey={(user) => user.id}
                    searchPlaceholder="Search name or email"
                    toolbar={
                        <>
                            <NativeSelect
                                aria-label="Role"
                                className="sm:w-48"
                                value={filters.role ?? ''}
                                placeholder="All roles"
                                options={roles.map((role) => ({
                                    value: role,
                                    label: humanize(role),
                                }))}
                                onChange={(event) =>
                                    apply({ role: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Status"
                                className="sm:w-40"
                                value={filters.status ?? ''}
                                placeholder="Any status"
                                options={[
                                    { value: 'active', label: 'Active' },
                                    { value: 'inactive', label: 'Deactivated' },
                                ]}
                                onChange={(event) =>
                                    apply({ status: event.target.value })
                                }
                            />
                        </>
                    }
                />
            </AdminPage>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: UserController.index() }],
};
