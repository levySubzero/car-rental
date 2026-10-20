import { Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MetadataRow } from '@/lib/metadata';
import { duplicateMetadataKeys, toMetadataKey } from '@/lib/metadata';

/**
 * Edits a `metadata` object as name/value rows (D20). Names are turned into snake_case
 * keys as they are typed. `errors` is the form's error bag: "name" and "name.{key}"
 * messages are shown.
 */
export function KeyValueField({
    name,
    label,
    value,
    onChange,
    errors,
    max = 30,
    description,
    disabled = false,
}: {
    name: string;
    label: ReactNode;
    value: MetadataRow[];
    onChange: (rows: MetadataRow[]) => void;
    errors: Record<string, string | undefined>;
    max?: number;
    description?: ReactNode;
    disabled?: boolean;
}) {
    const duplicates = duplicateMetadataKeys(value);

    const update = (index: number, row: Partial<MetadataRow>) =>
        onChange(
            value.map((current, i) =>
                i === index ? { ...current, ...row } : current,
            ),
        );

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}

            {value.length > 0 && (
                <div className="hidden grid-cols-[minmax(0,2fr)_minmax(0,3fr)_2.25rem] gap-2 text-xs text-muted-foreground sm:grid">
                    <span>Name</span>
                    <span>Value</span>
                </div>
            )}

            {value.map((row, index) => {
                const rowError =
                    errors[`${name}.${row.key}`] ??
                    (duplicates.includes(row.key)
                        ? 'This name is used twice; the last value wins.'
                        : undefined);

                return (
                    <div key={index} className="grid gap-1">
                        <div className="grid grid-cols-[minmax(0,1fr)_2.25rem] gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_2.25rem]">
                            <Input
                                value={row.key}
                                placeholder="top_speed_kmh"
                                aria-label={`Name ${index + 1}`}
                                aria-invalid={!!rowError}
                                disabled={disabled}
                                className="font-mono text-xs sm:text-sm"
                                onChange={(event) =>
                                    update(index, {
                                        key: toMetadataKey(event.target.value),
                                    })
                                }
                            />
                            <Input
                                value={row.value}
                                placeholder="250"
                                aria-label={`Value ${index + 1}`}
                                aria-invalid={!!rowError}
                                disabled={disabled}
                                maxLength={500}
                                className="col-start-1 row-start-2 sm:col-start-auto sm:row-start-auto"
                                onChange={(event) =>
                                    update(index, { value: event.target.value })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove ${row.key || 'row'}`}
                                disabled={disabled}
                                className="row-span-2 sm:row-span-1"
                                onClick={() =>
                                    onChange(
                                        value.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError message={rowError} />
                    </div>
                );
            })}

            {!disabled && value.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="justify-self-start"
                    onClick={() => onChange([...value, { key: '', value: '' }])}
                >
                    <Plus /> Add info
                </Button>
            )}
            <InputError message={errors[name]} />
        </div>
    );
}
