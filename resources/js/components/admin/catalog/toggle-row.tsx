import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/**
 * A labelled switch bound to useForm state (SwitchField is for uncontrolled <Form>s).
 */
export function ToggleRow({
    id,
    label,
    description,
    checked,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div className="grid gap-1">
                <Label htmlFor={id}>{label}</Label>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <Switch
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onChange}
            />
        </div>
    );
}
