import { Badge } from '@/components/ui/badge';
import type { AdminPolicy } from '@/types';

/**
 * Where a policy's text stands: live (with its version), a draft waiting, or never published.
 */
export function PolicyStatus({ policy }: { policy: AdminPolicy }) {
    return (
        <span className="flex flex-wrap items-center gap-1">
            {policy.published ? (
                <Badge variant="secondary">
                    Live · v{policy.published.version}
                </Badge>
            ) : (
                <Badge variant="outline">Not published</Badge>
            )}
            {policy.draft && (
                <Badge
                    variant={
                        policy.draft.has_confirm ? 'destructive' : 'outline'
                    }
                >
                    {policy.draft.has_confirm
                        ? 'Draft needs confirming'
                        : `Draft v${policy.draft.version}`}
                </Badge>
            )}
        </span>
    );
}
