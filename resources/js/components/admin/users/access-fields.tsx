import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { abilityLabel, humanize } from '@/lib/format';
import type { PermissionOptions } from '@/types';

function toggle(list: string[], values: string[], on: boolean): string[] {
    return on
        ? [...new Set([...list, ...values])]
        : list.filter((item) => !values.includes(item));
}

export function RoleFields({
    value,
    options,
    onChange,
    error,
    disabled = false,
}: {
    value: string[];
    options: string[];
    onChange: (roles: string[]) => void;
    error?: string;
    disabled?: boolean;
}) {
    return (
        <fieldset className="grid gap-3" disabled={disabled}>
            <legend className="mb-1 text-sm font-medium">Roles</legend>
            {options.map((role) => (
                <Label
                    key={role}
                    className="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        checked={value.includes(role)}
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            onChange(toggle(value, [role], checked === true))
                        }
                    />
                    {humanize(role)}
                </Label>
            ))}
            <InputError message={error} />
        </fieldset>
    );
}

/**
 * Permissions grouped by module, with a select-all box per module.
 */
export function PermissionChecklist({
    value,
    options,
    onChange,
    error,
    disabled = false,
}: {
    value: string[];
    options: PermissionOptions;
    onChange: (permissions: string[]) => void;
    error?: string;
    disabled?: boolean;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(options).map(([module, permissions]) => {
                    const selected = permissions.filter((permission) =>
                        value.includes(permission),
                    ).length;

                    return (
                        <div key={module} className="grid content-start gap-2">
                            <Label className="flex items-center gap-2">
                                <Checkbox
                                    disabled={disabled}
                                    checked={
                                        selected === permissions.length
                                            ? true
                                            : selected > 0
                                              ? 'indeterminate'
                                              : false
                                    }
                                    onCheckedChange={(checked) =>
                                        onChange(
                                            toggle(
                                                value,
                                                permissions,
                                                checked === true,
                                            ),
                                        )
                                    }
                                />
                                {humanize(module)}
                            </Label>
                            <div className="grid gap-2 pl-6">
                                {permissions.map((permission) => (
                                    <Label
                                        key={permission}
                                        className="flex items-center gap-2 font-normal"
                                    >
                                        <Checkbox
                                            checked={value.includes(permission)}
                                            disabled={disabled}
                                            onCheckedChange={(checked) =>
                                                onChange(
                                                    toggle(
                                                        value,
                                                        [permission],
                                                        checked === true,
                                                    ),
                                                )
                                            }
                                        />
                                        {abilityLabel(permission)}
                                    </Label>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
            <InputError message={error} />
        </div>
    );
}

/**
 * Direct permissions on a user, granted on top of whatever their roles give.
 */
export function PermissionFields(
    props: Parameters<typeof PermissionChecklist>[0],
) {
    return (
        <fieldset className="grid gap-3" disabled={props.disabled}>
            <legend className="mb-1 text-sm font-medium">
                Extra permissions
                <span className="block text-xs font-normal text-muted-foreground">
                    Granted in addition to the permissions of the user's roles.
                </span>
            </legend>
            <PermissionChecklist {...props} />
        </fieldset>
    );
}
