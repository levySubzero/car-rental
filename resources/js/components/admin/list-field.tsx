import { Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Edits a short list of strings (phone numbers, email recipients).
 * `errors` is the form's error bag; messages for "name.0", "name.1", ... are shown per row.
 */
export function StringListField({
    name,
    label,
    value,
    onChange,
    errors,
    max = 10,
    addLabel = 'Add',
    placeholder,
    type = 'text',
    description,
}: {
    name: string;
    label: ReactNode;
    value: string[];
    onChange: (value: string[]) => void;
    errors: Record<string, string | undefined>;
    max?: number;
    addLabel?: string;
    placeholder?: string;
    type?: string;
    description?: ReactNode;
}) {
    const update = (index: number, item: string) =>
        onChange(value.map((current, i) => (i === index ? item : current)));

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
            {value.map((item, index) => (
                <div key={index} className="grid gap-1">
                    <div className="flex gap-2">
                        <Input
                            type={type}
                            value={item}
                            placeholder={placeholder}
                            aria-label={`${typeof label === 'string' ? label : name} ${index + 1}`}
                            aria-invalid={!!errors[`${name}.${index}`]}
                            onChange={(event) =>
                                update(index, event.target.value)
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove"
                            onClick={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                        >
                            <X />
                        </Button>
                    </div>
                    <InputError message={errors[`${name}.${index}`]} />
                </div>
            ))}
            {value.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="justify-self-start"
                    onClick={() => onChange([...value, ''])}
                >
                    <Plus /> {addLabel}
                </Button>
            )}
            <InputError message={errors[name]} />
        </div>
    );
}
