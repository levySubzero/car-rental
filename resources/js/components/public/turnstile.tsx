import { useEffect, useRef } from 'react';

type TurnstileApi = {
    render: (element: HTMLElement, options: Record<string, unknown>) => string;
    remove: (widgetId: string) => void;
};

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
let loading: Promise<void> | null = null;

function loadScript(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    loading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile failed to load'));
        };
        document.head.appendChild(script);
    });

    return loading;
}

/**
 * Cloudflare Turnstile (M7.15): the server checks the token with App\Support\Rules\Turnstile.
 * Renders nothing without a site key, as in tests.
 */
export function Turnstile({
    siteKey,
    onToken,
}: {
    siteKey: string | null;
    onToken: (token: string) => void;
}) {
    const element = useRef<HTMLDivElement>(null);
    const callback = useRef(onToken);
    callback.current = onToken;

    useEffect(() => {
        if (!siteKey || !element.current) {
            return;
        }

        let widget: string | null = null;
        let cancelled = false;

        loadScript()
            .then(() => {
                if (cancelled || !element.current || !window.turnstile) {
                    return;
                }

                widget = window.turnstile.render(element.current, {
                    sitekey: siteKey,
                    theme: 'dark',
                    callback: (token: string) => callback.current(token),
                    'expired-callback': () => callback.current(''),
                    'error-callback': () => callback.current(''),
                });
            })
            .catch(() => callback.current(''));

        return () => {
            cancelled = true;

            if (widget && window.turnstile) {
                window.turnstile.remove(widget);
            }
        };
    }, [siteKey]);

    return siteKey ? <div ref={element} className="min-h-[65px]" /> : null;
}
