import { BrandIcon, SOCIAL_LABELS } from '@/components/public/icons';
import { useSite } from '@/hooks/use-site';
import { cn } from '@/lib/utils';

export function SocialLinks({
    className,
    iconClassName = 'size-4',
}: {
    className?: string;
    iconClassName?: string;
}) {
    const { site } = useSite();

    return (
        <ul className={cn('flex items-center gap-3', className)}>
            {Object.entries(site.socials).map(([platform, url]) => (
                <li key={platform}>
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block text-white/70 transition-colors hover:text-gold"
                        aria-label={`${SOCIAL_LABELS[platform] ?? platform} (opens in a new tab)`}
                    >
                        <BrandIcon name={platform} className={iconClassName} />
                    </a>
                </li>
            ))}
        </ul>
    );
}
