import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { sidebarOpen: isOpen, branding } = usePage().props;

    // Update browser icons after an in-place branding save as well as on navigation.
    useEffect(() => {
        if (!branding) return;
        document.querySelectorAll<HTMLLinkElement>('link[rel="icon"], link[rel="apple-touch-icon"]').forEach((link) => {
            link.href = branding.logo_url;
            link.removeAttribute('type');
        });
    }, [branding?.logo_url]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <TooltipProvider>
            <div className="mx-auto w-full max-w-screen-2xl">
                <SidebarProvider defaultOpen={isOpen} className="flex-col">{children}</SidebarProvider>
            </div>
        </TooltipProvider>
    );
}
