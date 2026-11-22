import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            branding?: { institute_id: number | null; name: string; logo_url: string; site_icon_url: string; custom_site_icon: boolean };
            [key: string]: unknown;
        };
    }
}
