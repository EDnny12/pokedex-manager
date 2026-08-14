import type { PageProps } from '@inertiajs/core';

export interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string;
}

export interface AppPageProps extends PageProps {
    auth: {
        user: AuthenticatedUser;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
}
