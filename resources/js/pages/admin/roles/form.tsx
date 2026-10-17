import { Head, Link, router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { TextareaField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { PermissionChecklist } from '@/components/admin/users/access-fields';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import type { AdminRole, PermissionOptions } from '@/types';

/**
 * Create a role (`role` is null) or edit one.
 */
export default function RoleForm({
    role,
    permissionOptions,
}: {
    role: AdminRole | null;
    permissionOptions: PermissionOptions;
}) {
    const can = useCan();
    const locked = role?.is_system ?? false;
    const editable =
        !locked && (role === null ? can('roles.create') : can('roles.update'));

    const form = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: role?.permissions ?? [],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (role === null) {
            form.post(RoleController.store.url());
        } else {
            form.put(RoleController.update.url(role.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title={role?.name ?? 'New role'} />
            <AdminPage>
                <PageHeader
                    title={role?.name ?? 'New role'}
                    description={
                        role
                            ? `${role.users_count ?? 0} ${role.users_count === 1 ? 'user has' : 'users have'} this role.`
                            : 'Name the role and choose what it can do.'
                    }
                    actions={
                        role &&
                        !locked &&
                        can('roles.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete the ${role.name} role?`}
                                description={
                                    role.users_count
                                        ? 'Move its users to another role first.'
                                        : 'This cannot be undone.'
                                }
                                confirmLabel="Delete role"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        RoleController.destroy.url(role.id),
                                    )
                                }
                            />
                        )
                    }
                />

                {locked && (
                    <Alert className="max-w-4xl">
                        <Lock />
                        <AlertDescription>
                            The super admin role is built in. It always has
                            every permission and cannot be changed or deleted.
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit} className="grid max-w-4xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Role</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <TextField
                                name="name"
                                label="Name"
                                required
                                disabled={!editable}
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <TextareaField
                                name="description"
                                label="Description"
                                rows={2}
                                disabled={!editable}
                                value={form.data.description}
                                error={form.errors.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Permissions</CardTitle>
                            <CardDescription>
                                Everyone with this role gets these permissions.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PermissionChecklist
                                value={form.data.permissions}
                                options={permissionOptions}
                                disabled={!editable}
                                onChange={(permissions) =>
                                    form.setData('permissions', permissions)
                                }
                                error={form.errors.permissions}
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (role !== null && !form.isDirty)
                                    }
                                >
                                    {role ? 'Save role' : 'Create role'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={RoleController.index()}>
                                        Cancel
                                    </Link>
                                </Button>
                            </CardFooter>
                        )}
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [{ title: 'Roles', href: RoleController.index() }],
};
