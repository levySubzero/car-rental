import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSite } from '@/hooks/use-site';
import { update } from '@/routes/currency';

/**
 * Display currency only (M6.13): renters always pay in KES.
 */
export function CurrencySwitcher() {
    const { currency } = useSite();

    if (currency.options.length < 2) {
        return null;
    }

    const choose = (code: string) =>
        router.post(
            update().url,
            { currency: code },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="inline-flex items-center gap-1 rounded px-1.5 py-1 text-xs font-semibold tracking-wider text-white/80 transition-colors hover:text-gold focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                aria-label={`Display currency: ${currency.selected}`}
            >
                {currency.selected}
                <ChevronDown className="size-3" aria-hidden />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="public dark min-w-40">
                <DropdownMenuRadioGroup
                    value={currency.selected}
                    onValueChange={choose}
                >
                    {currency.options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.code}
                            value={option.code}
                        >
                            <span className="w-10 font-semibold">
                                {option.code}
                            </span>
                            <span className="text-muted-foreground">
                                {option.name}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
