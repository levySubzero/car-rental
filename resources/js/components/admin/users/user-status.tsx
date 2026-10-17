import { Badge } from '@/components/ui/badge';
import type { AdminUser } from '@/types';

export function UserStatus({ user }: { user: AdminUser }) {
    if (!user.is_active) {
        return <Badge variant="destructive">Deactivated</Badge>;
    }

    if (!user.email_verified) {
        return <Badge variant="outline">Invited</Badge>;
    }

    if (!user.two_factor_enabled) {
        return <Badge variant="outline">2FA pending</Badge>;
    }

    return <Badge>Active</Badge>;
}
