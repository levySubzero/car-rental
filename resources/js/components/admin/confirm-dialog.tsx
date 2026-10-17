import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { cn } from '@/lib/utils';

/**
 * Asks before a destructive or important action. `onConfirm` may return a promise;
 * the dialog stays open, with the button disabled, until it settles.
 */
export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel = 'Confirm',
    destructive = false,
    onConfirm,
}: {
    trigger: ReactNode;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    destructive?: boolean;
    onConfirm: () => void | Promise<unknown>;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const confirm = async (event: React.MouseEvent) => {
        event.preventDefault();
        setBusy(true);

        try {
            await onConfirm();
            setOpen(false);
        } finally {
            setBusy(false);
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && (
                        <AlertDialogDescription>
                            {description}
                        </AlertDialogDescription>
                    )}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={busy}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={busy}
                        onClick={confirm}
                        className={cn(
                            destructive &&
                                'bg-destructive text-white hover:bg-destructive/90',
                        )}
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
