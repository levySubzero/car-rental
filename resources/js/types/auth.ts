export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_staff?: boolean;
    [key: string]: unknown;
};

import type { Permission } from './permissions';

export type Auth = {
    user: User;
    // Permission names, or ['*'] for a super admin. UI hints only; the server authorizes.
    permissions: (Permission | '*')[];
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
