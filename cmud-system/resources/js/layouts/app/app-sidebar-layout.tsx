import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { AppTopHeader } from '@/components/app-top-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppTopHeader />
            <div className="flex min-h-[calc(100svh-var(--app-header-height,0px))] w-full flex-1">
                <AppSidebar />
                <AppContent variant="sidebar" className="min-h-[calc(100svh-var(--app-header-height,0px))] min-w-0 overflow-x-clip md:peer-data-[variant=inset]:min-h-[calc(100svh-var(--app-header-height,0px)-1rem)]">
                    {breadcrumbs.length > 0 && <div className="flex min-h-12 shrink-0 items-center border-b border-slate-100 px-4 sm:px-6 dark:border-slate-800"><Breadcrumbs breadcrumbs={breadcrumbs} /></div>}
                    {children}
                </AppContent>
            </div>
        </AppShell>
    );
}
