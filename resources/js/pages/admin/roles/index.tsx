import { Head, Link } from '@inertiajs/react';
import { Lock, Plus } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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
import { useCan } from '@/hooks/use-can';
import { permissions as allPermissions } from '@/types/permissions';
import type { AdminRole } from '@/types';

export default function RolesIndex({ roles }: { roles: AdminRole[] }) {
    const can = useCan();

    return (
        <>
            <Head title="Roles" />
            <AdminPage>
                <PageHeader
                    title="Roles"
                    description="A role is a named set of permissions. Give it to users on their user page."
                    actions={
                        can('roles.create') && (
                            <Button asChild>
                                <Link href={RoleController.create()}>
                                    <Plus /> New role
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="overflow-hidden rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead>Users</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div className="grid">
                                            <Link
                                                href={RoleController.edit(
                                                    role.id,
                                                )}
                                                className="inline-flex items-center gap-1.5 font-medium hover:underline"
                                            >
                                                {role.is_system && (
                                                    <Lock className="size-3.5" />
                                                )}
                                                {role.name}
                                            </Link>
                                            {role.description && (
                                                <span className="text-xs text-muted-foreground">
                                                    {role.description}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {role.is_system ? (
                                            <Badge variant="secondary">
                                                All access
                                            </Badge>
                                        ) : (
                                            `${role.permissions_count} of ${allPermissions.length}`
                                        )}
                                    </TableCell>
                                    <TableCell>{role.users_count}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </AdminPage>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: RoleController.index() }],
};
