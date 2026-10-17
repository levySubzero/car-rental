import { Head, Link, useForm } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    PermissionFields,
    RoleFields,
} from '@/components/admin/users/access-fields';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import type { PermissionOptions } from '@/types';

export default function CreateUser({
    roleOptions,
    permissionOptions,
}: {
    roleOptions: string[];
    permissionOptions: PermissionOptions;
}) {
    const can = useCan();
    const form = useForm({
        name: '',
        email: '',
        roles: [] as string[],
        permissions: [] as string[],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            name: data.name,
            email: data.email,
            ...(can('users.assign-roles') ? { roles: data.roles } : {}),
            ...(can('users.grant-permissions')
                ? { permissions: data.permissions }
                : {}),
        }));
        form.post(UserController.store.url());
    };

    return (
        <>
            <Head title="Invite user" />
            <AdminPage>
                <PageHeader
                    title="Invite user"
                    description="They will get an email to set a password, then set up two-factor authentication."
                />
                <form onSubmit={submit} className="grid max-w-4xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                name="name"
                                label="Name"
                                required
                                autoComplete="off"
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
                                autoComplete="off"
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
                    </Card>

                    {(can('users.assign-roles') ||
                        can('users.grant-permissions')) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Access</CardTitle>
                                <CardDescription>
                                    What this person can see and change.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-8">
                                {can('users.assign-roles') && (
                                    <RoleFields
                                        value={form.data.roles}
                                        options={roleOptions}
                                        onChange={(roles) =>
                                            form.setData('roles', roles)
                                        }
                                        error={form.errors.roles}
                                    />
                                )}
                                {can('users.grant-permissions') && (
                                    <PermissionFields
                                        value={form.data.permissions}
                                        options={permissionOptions}
                                        onChange={(permissions) =>
                                            form.setData(
                                                'permissions',
                                                permissions,
                                            )
                                        }
                                        error={form.errors.permissions}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Send invitation
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={UserController.index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Users', href: UserController.index() },
        { title: 'Invite', href: UserController.create() },
    ],
};
