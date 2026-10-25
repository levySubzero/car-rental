/*
 * Browser error reporting (M12.10). Loaded only when VITE_SENTRY_DSN is set, and on demand,
 * so pages without it pay nothing. The same secrets as on the server are scrubbed (M12.4):
 * booking tokens in URLs never leave the browser.
 */
type Scrubbable = { url?: string; data?: unknown };

export function scrubUrl(value: string): string {
    return value
        .replace(/\/bookings\/[A-Za-z0-9]{20,}/g, '/bookings/[token]')
        .replace(
            /\/hooks\/mobile\/(stk|c2b)\/[^/?\s]+/g,
            '/hooks/mobile/$1/[secret]',
        );
}

export function initSentry(): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN as string | undefined;

    if (!dsn || typeof window === 'undefined') {
        return;
    }

    void import('@sentry/react').then((Sentry) =>
        Sentry.init({
            dsn,
            environment: import.meta.env.VITE_SENTRY_ENVIRONMENT as
                | string
                | undefined,
            release: import.meta.env.VITE_SENTRY_RELEASE as string | undefined,
            beforeSend(event) {
                if (event.request) {
                    const request = event.request as Scrubbable;
                    request.url = request.url
                        ? scrubUrl(request.url)
                        : request.url;
                    delete request.data;
                }

                return event;
            },
            beforeBreadcrumb(breadcrumb) {
                if (breadcrumb.message) {
                    breadcrumb.message = scrubUrl(breadcrumb.message);
                }

                for (const key of ['url', 'from', 'to'] as const) {
                    const value = breadcrumb.data?.[key];

                    if (typeof value === 'string') {
                        breadcrumb.data = {
                            ...breadcrumb.data,
                            [key]: scrubUrl(value),
                        };
                    }
                }

                return breadcrumb;
            },
        }),
    );
}
