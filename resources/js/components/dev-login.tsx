import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { login as devLogin } from '@/routes/dev';

export type DevUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

/**
 * Local-only: sign in as any active user without a password. The server only
 * sends `users` (and only accepts the request) when APP_ENV=local.
 */
export function DevLogin({ users }: { users: DevUser[] }) {
    const [userId, setUserId] = useState<string>('');
    const [processing, setProcessing] = useState(false);

    return (
        <div className="grid gap-3 rounded-lg border border-dashed border-amber-500/60 bg-amber-50 p-4 dark:bg-amber-950/20">
            <Label
                htmlFor="dev-login-user"
                className="text-amber-800 dark:text-amber-300"
            >
                Development: sign in as
            </Label>
            <div className="flex gap-2">
                <Select value={userId} onValueChange={setUserId}>
                    <SelectTrigger
                        id="dev-login-user"
                        className="min-w-0 flex-1 bg-background"
                    >
                        <SelectValue placeholder="Choose a user" />
                    </SelectTrigger>
                    <SelectContent>
                        {users.map((user) => (
                            <SelectItem key={user.id} value={String(user.id)}>
                                {user.name}
                                <span className="text-muted-foreground">
                                    {' '}
                                    ·{' '}
                                    {user.roles.length > 0
                                        ? user.roles.join(', ')
                                        : user.email}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    variant="outline"
                    disabled={userId === '' || processing}
                    data-test="dev-login-button"
                    onClick={() =>
                        router.post(
                            devLogin.url(),
                            { user_id: Number(userId) },
                            {
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                            },
                        )
                    }
                >
                    Go
                </Button>
            </div>
        </div>
    );
}
