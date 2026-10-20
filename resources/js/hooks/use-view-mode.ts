import { useState } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';

export type ViewMode = 'table' | 'grid';

const read = (key: string): ViewMode | null => {
    try {
        const value = localStorage.getItem(`view:${key}`);

        return value === 'table' || value === 'grid' ? value : null;
    } catch {
        return null;
    }
};

/**
 * Table or grid for a list page. The choice is remembered per page in this browser;
 * until one is made, phones get the grid and larger screens the table.
 */
export function useViewMode(key: string): [ViewMode, (mode: ViewMode) => void] {
    const isMobile = useIsMobile();
    const [chosen, setChosen] = useState<ViewMode | null>(() =>
        typeof window === 'undefined' ? null : read(key),
    );

    const choose = (mode: ViewMode) => {
        setChosen(mode);

        try {
            localStorage.setItem(`view:${key}`, mode);
        } catch {
            // Private mode or blocked storage: the choice lasts for this visit only.
        }
    };

    return [chosen ?? (isMobile ? 'grid' : 'table'), choose];
}
