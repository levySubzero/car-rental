import { Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { UserStatus } from '@/components/admin/users/user-status';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { formatDateTime, humanize } from '@/lib/format';
import type { AdminUser } from '@/types';

/**
 * A staff member in the users grid view.
 */
export function UserCard({ user }: { user: AdminUser }) {
    const initials = useInitials();

    return (
        <article className="flex h-full flex-col gap-3 rounded-lg border bg-card p-4">
            <div className="flex items-start gap-3">
                <Avatar className="size-10 shrink-0">
                    <AvatarFallback>{initials(user.name)}</AvatarFallback>
                </Avatar>
                <div className="grid min-w-0 flex-1">
                    <Link
                        href={UserController.edit(user.id)}
                        className="truncate font-medium hover:underline"
                    >
                        {user.name}
                    </Link>
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                </div>
                <UserStatus user={user} />
            </div>
            <div className="flex flex-wrap gap-1">
                {(user.roles ?? []).map((role) => (
                    <Badge key={role} variant="secondary">
                        {humanize(role)}
                    </Badge>
                ))}
            </div>
            <p className="mt-auto text-xs text-muted-foreground">
                Last sign-in: {formatDateTime(user.last_login_at)}
            </p>
        </article>
    );
}
