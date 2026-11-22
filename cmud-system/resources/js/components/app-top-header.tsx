import { Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { dashboard, logout } from '@/routes';

const demoUser = { name: 'Ariful Islam', designation: 'Administrator', full_name: undefined, avatar: undefined };
const logoutClass = 'inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-lg border border-[#17375C] bg-[#17375C] px-3 text-xs font-semibold text-white transition-colors hover:border-[#254878] hover:bg-[#254878] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600';

export function AppTopHeader() {
    const { auth, branding } = usePage().props;
    const isDemo = !auth?.user;
    const user = auth?.user || demoUser;
    const displayName = user.full_name || user.name;
    const initials = useInitials();
    const [showLogout, setShowLogout] = useState(false);
    const headerRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const header = headerRef.current;
        const shell = header?.closest<HTMLElement>('[data-slot="sidebar-wrapper"]');
        if (!header || !shell) return;

        const updateHeight = () => shell.style.setProperty('--app-header-height', header.getBoundingClientRect().height + 'px');
        updateHeight();
        const observer = new ResizeObserver(updateHeight);
        observer.observe(header);
        return () => {
            observer.disconnect();
            shell.style.removeProperty('--app-header-height');
        };
    }, []);

    return (
        <>
            <header ref={headerRef} className="sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-3 sm:px-6 sm:py-4 dark:border-slate-800 dark:bg-slate-950">
                <Link href={dashboard()} className="shrink-0 rounded-xl focus-visible:outline-2 focus-visible:outline-teal-600" aria-label="CMUD home">
                    <img src={branding?.logo_url ?? '/apple-touch-icon.png'} alt={`${branding?.name ?? 'CMUD'} logo`} className="size-10 object-contain" />
                </Link>
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                    <Avatar className="size-10 shrink-0">
                        <AvatarImage src={user.avatar} alt={displayName} />
                        <AvatarFallback className="bg-teal-50 text-sm font-medium text-teal-800 dark:bg-teal-950 dark:text-teal-200">{initials(displayName)}</AvatarFallback>
                    </Avatar>
                    <div className="grid min-w-0 max-w-52 gap-0.5 text-right">
                        <span className="truncate text-sm font-medium text-slate-800 dark:text-slate-100" title={displayName}>{displayName}</span>
                        <span className="truncate text-xs text-slate-500 dark:text-slate-400">{user.designation || 'Designation not set'}</span>
                    </div>
                    {isDemo
                        ? <button type="button" onClick={() => setShowLogout(true)} aria-label="Log out" title="Log out" className={logoutClass}><LogOut size={16} aria-hidden="true" /><span>Log out</span></button>
                        : <Link href={logout()} as="button" onClick={() => router.flushAll()} aria-label="Log out" title="Log out" data-test="logout-button" className={logoutClass}><LogOut size={16} aria-hidden="true" /><span>Log out</span></Link>}
                </div>
            </header>
            <Dialog open={showLogout} onOpenChange={setShowLogout}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Log out · Demo</DialogTitle>
                        <DialogDescription>This is a demo account. Log out will end the user session once the login API is connected.</DialogDescription>
                    </DialogHeader>
                    <button type="button" onClick={() => setShowLogout(false)} className="justify-self-end rounded-lg bg-teal-50 px-4 py-2 text-sm font-medium text-teal-800 hover:bg-teal-100">Close</button>
                </DialogContent>
            </Dialog>
        </>
    );
}
