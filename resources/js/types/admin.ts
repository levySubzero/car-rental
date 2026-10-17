// Shapes of the admin API Resources (app/Http/Resources/Admin). Hand-maintained (TECHNICAL_PLAN §3).

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    email_verified: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    created_at: string | null;
    roles?: string[];
    permissions?: string[];
};

export type AdminRole = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    users_count?: number;
    permissions_count?: number;
    permissions?: string[];
};

// Module name → permission names, e.g. { users: ['users.view', ...] }
export type PermissionOptions = Record<string, string[]>;

export type ActivityEntry = {
    id: number;
    log: string | null;
    event: string | null;
    description: string;
    subject: { type: string; id: number | string; label: string | null } | null;
    causer: { id: number | string; label: string | null } | null;
    changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    };
    properties: Record<string, unknown>;
    created_at: string | null;
};

export type SettingsTab = {
    key: string;
    label: string;
};
