const dateTime = new Intl.DateTimeFormat('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDateTime(value: string | null | undefined): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

// "users.assign-roles" → "Assign roles"
export function abilityLabel(permission: string): string {
    const ability = permission.split('.').slice(1).join(' ');
    const words = ability.replace(/[-.]/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

// "content-editor" → "Content editor"
export function humanize(value: string): string {
    const words = value.replace(/[-_.]/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
