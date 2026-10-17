import { Head, router, useForm, usePage } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    PermissionFields,
    RoleFields,
} from '@/components/admin/users/access-fields';
import { UserStatus } from '@/components/admin/users/user-status';
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
import { formatDateTime } from '@/lib/format';
import type { AdminUser, PermissionOptions } from '@/types';

export default function EditUser({
    user,
    roleOptions,
    permissionOptions,
}: {
    user: AdminUser;
    roleOptions: string[];
    permissionOptions: PermissionOptions;
}) {
    const can = useCan();
    const { auth } = usePage().props;
    const isSelf = auth.user.id === user.id;

    return (
        <>
            <Head title={user.name} />
            <AdminPage>
                <PageHeader
                    title={user.name}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <UserStatus user={user} />
                            Last sign-in {formatDateTime(user.last_login_at)}
                        </span>
                    }
                    actions={!isSelf && <AccountActions user={user} />}
                />
                <div className="grid max-w-4xl gap-6">
                    <DetailsCard user={user} editable={can('users.update')} />
                    {(can('users.assign-roles') ||
                        can('users.grant-permissions')) && (
                        <AccessCard
                            user={user}
                            roleOptions={roleOptions}
                            permissionOptions={permissionOptions}
                        />
                    )}
                </div>
            </AdminPage>
        </>
    );
}

function DetailsCard({
    user,
    editable,
}: {
    user: AdminUser;
    editable: boolean;
}) {
    const form = useForm({ name: user.name, email: user.email });

    return (
        <Card>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(UserController.update.url(user.id), {
                        preserveScroll: true,
                    });
                }}
            >
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        Changing the email asks the user to verify the new
                        address.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 py-6 sm:grid-cols-2">
                    <TextField
                        name="name"
                        label="Name"
                        required
                        disabled={!editable}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        error={form.errors.name}
                    />
                    <TextField
                        name="email"
                        label="Email"
                        type="email"
                        required
                        disabled={!editable}
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData(
                                'email',
                                event.target.value.toLowerCase(),
                            )
                        }
                        error={form.errors.email}
                    />
                </CardContent>
                {editable && (
                    <CardFooter>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.isDirty}
                        >
                            Save details
                        </Button>
                    </CardFooter>
                )}
            </form>
        </Card>
    );
}

function AccessCard({
    user,
    roleOptions,
    permissionOptions,
}: {
    user: AdminUser;
    roleOptions: string[];
    permissionOptions: PermissionOptions;
}) {
    const can = useCan();
    const form = useForm({
        roles: user.roles ?? [],
        permissions: user.permissions ?? [],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...(can('users.assign-roles') ? { roles: data.roles } : {}),
            ...(can('users.grant-permissions')
                ? { permissions: data.permissions }
                : {}),
        }));
        form.put(UserController.access.url(user.id), { preserveScroll: true });
    };

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle>Access</CardTitle>
                    <CardDescription>
                        Manage what each role grants on the Roles page.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-8 py-6">
                    <RoleFields
                        value={form.data.roles}
                        options={roleOptions}
                        disabled={!can('users.assign-roles')}
                        onChange={(roles) => form.setData('roles', roles)}
                        error={form.errors.roles}
                    />
                    <PermissionFields
                        value={form.data.permissions}
                        options={permissionOptions}
                        disabled={!can('users.grant-permissions')}
                        onChange={(permissions) =>
                            form.setData('permissions', permissions)
                        }
                        error={form.errors.permissions}
                    />
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save access
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function AccountActions({ user }: { user: AdminUser }) {
    const can = useCan();

    return (
        <>
            {can('users.update') && !user.email_verified && (
                <Button
                    variant="outline"
                    onClick={() =>
                        router.post(
                            UserController.resendInvitation.url(user.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Resend invitation
                </Button>
            )}
            {can('users.update') && (
                <ConfirmDialog
                    trigger={
                        <Button variant="outline">
                            {user.is_active ? 'Deactivate' : 'Activate'}
                        </Button>
                    }
                    title={
                        user.is_active
                            ? `Deactivate ${user.name}?`
                            : `Activate ${user.name}?`
                    }
                    description={
                        user.is_active
                            ? 'They are signed out and cannot sign in again until reactivated.'
                            : 'They can sign in again with their existing password.'
                    }
                    confirmLabel={user.is_active ? 'Deactivate' : 'Activate'}
                    destructive={user.is_active}
                    onConfirm={() =>
                        router.put(
                            UserController.toggleActive.url(user.id),
                            { active: !user.is_active },
                            { preserveScroll: true },
                        )
                    }
                />
            )}
            {can('users.delete') && (
                <ConfirmDialog
                    trigger={<Button variant="destructive">Delete</Button>}
                    title={`Delete ${user.name}?`}
                    description="This removes the account permanently. Their past actions stay in the activity log."
                    confirmLabel="Delete user"
                    destructive
                    onConfirm={() =>
                        router.delete(UserController.destroy.url(user.id))
                    }
                />
            )}
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [{ title: 'Users', href: UserController.index() }],
};
