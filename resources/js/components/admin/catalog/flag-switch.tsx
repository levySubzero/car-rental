import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Switch } from '@/components/ui/switch';

/**
 * An inline on/off switch that saves immediately with PUT { value }, e.g. a car's
 * availability in the list. Shows the new state at once and reverts if the save fails.
 */
export function FlagSwitch({
    url,
    checked,
    label,
    disabled = false,
}: {
    url: string;
    checked: boolean;
    label: string;
    disabled?: boolean;
}) {
    const [value, setValue] = useState(checked);
    const [saving, setSaving] = useState(false);

    const change = (next: boolean) => {
        setValue(next);
        setSaving(true);

        router.put(
            url,
            { value: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setValue(!next),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Switch
            checked={value}
            onCheckedChange={change}
            disabled={disabled || saving}
            aria-label={label}
        />
    );
}
