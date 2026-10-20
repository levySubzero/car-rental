import { Badge } from '@/components/ui/badge';
import type { AdminContactMessage } from '@/types';

const VARIANTS = {
    new: 'default',
    in_progress: 'secondary',
    resolved: 'outline',
    spam: 'outline',
} as const;

export function ContactStatusBadge({
    message,
}: {
    message: Pick<AdminContactMessage, 'status' | 'status_label'>;
}) {
    return (
        <Badge variant={VARIANTS[message.status]}>{message.status_label}</Badge>
    );
}
