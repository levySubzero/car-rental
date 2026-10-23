import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const action = data.action;

            toast[data.type](
                data.message,
                action
                    ? {
                          duration: 20_000,
                          action: {
                              label: action.label,
                              onClick: () =>
                                  window.open(
                                      action.url,
                                      '_blank',
                                      'noopener,noreferrer',
                                  ),
                          },
                      }
                    : undefined,
            );
        });
    }, []);
}
