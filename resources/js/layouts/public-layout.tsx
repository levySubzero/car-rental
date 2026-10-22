import type { ReactNode } from 'react';
import { FloatingContact } from '@/components/public/floating-contact';
import { SiteFooter } from '@/components/public/site-footer';
import { SiteHeader } from '@/components/public/site-header';
import { TopBar } from '@/components/public/top-bar';

/**
 * Every public page (M7.2). `.public` and `.dark` give the subtree the brand palette,
 * whatever appearance staff chose for the admin.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="public dark flex min-h-screen flex-col bg-background text-foreground antialiased">
            <a
                href="#main"
                className="sr-only z-50 rounded bg-gold px-4 py-2 font-semibold text-ink focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >
                Skip to content
            </a>
            <TopBar />
            <SiteHeader />
            {/* Room for the phone's bottom contact bar. */}
            <main id="main" className="flex-1 pb-14 sm:pb-0">
                {children}
            </main>
            <div className="pb-14 sm:pb-0">
                <SiteFooter />
            </div>
            <FloatingContact />
        </div>
    );
}
