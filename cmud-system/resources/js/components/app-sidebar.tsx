import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardCheck,
    LayoutGrid,
    UserRoundPlus,
    Settings,
    Settings2,
    Building2,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { useCurrentUrl } from '@/hooks/use-current-url';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Students',
        href: '/academy/students',
        icon: ClipboardCheck,
    },
    {
        title: 'Student Admission',
        href: '/academy/students/admission',
        icon: UserRoundPlus,
    },
    {
        title: 'Academy Management',
        href: '/academy/manage',
        icon: Settings2,
    },
    {
        title: 'Class routine',
        href: '/academy/class-routine',
        icon: CalendarDays,
    },
];

export function AppSidebar() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="2xl:left-[calc((100vw-96rem)/2)]"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <SidebarGroup className="mt-auto px-2 py-0">
                    <SidebarGroupLabel className="flex items-center gap-2">
                        <Settings size={14} /> Settings
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl('/settings/institute')}
                                tooltip={{ children: 'Institute' }}
                            >
                                <Link href="/settings/institute" prefetch>
                                    <Building2 />
                                    <span>Institute</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(
                                    '/settings/user-control',
                                )}
                                tooltip={{ children: 'User Control' }}
                            >
                                <Link href="/settings/user-control" prefetch>
                                    <Users />
                                    <span>User Control</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
