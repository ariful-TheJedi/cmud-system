import {
    LayoutGrid,
    ClipboardCheck,
    FileCheck2,
    Award,
    Wallet,
    CreditCard,
    ReceiptText,
    UserRoundPlus,
    Settings,
    BookOpen,
    GraduationCap,
    University,
    Layers3,
    BriefcaseBusiness,
    Building2,
    Users,
    Boxes,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    return (
        <>
        <SidebarTrigger className="fixed top-[calc(var(--app-header-height,0px)+1rem)] left-2 z-20 size-8 rounded-lg border border-slate-200 bg-white shadow-sm md:hidden" />
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="top-[var(--app-header-height,0px)] h-[calc(100svh-var(--app-header-height,0px))] 2xl:left-[calc((100vw-96rem)/2)]"
        >
            <SidebarHeader className="flex-row items-center justify-between border-b border-slate-200 px-3 py-3 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0 dark:border-slate-800">
                <span className="text-xs font-semibold tracking-wide text-slate-500 uppercase group-data-[collapsible=icon]:hidden">Navigation</span>
                <SidebarTrigger className="shrink-0 text-slate-500" />
            </SidebarHeader>
            <SidebarContent className="py-3">
                <NavMain items={mainNavItems} />
                <NavMain title="Academy" icon={University} items={[
                    { title: 'Students', href: '/academy/students', icon: ClipboardCheck },
                    { title: 'Admission', href: '/academy/students/admission', icon: UserRoundPlus },
                    { title: 'Courses', href: '/academy/manage/courses', icon: GraduationCap },
                    { title: 'Batches', href: '/academy/manage/batches', icon: Layers3 },
                    { title: 'Modules & subjects', href: '/academy/manage/subjects', icon: BookOpen },
                    { title: 'Teachers', href: '/academy/manage/teachers', icon: BriefcaseBusiness },
                    { title: 'Exam Control', href: '/academy/students/exam-control', icon: FileCheck2 },
                    { title: 'Certificates', href: '/academy/certificates', icon: Award },
                ]} />
                <NavMain items={[
                    { title: 'Stuff', href: '/stuff', icon: BriefcaseBusiness },
                ]} />
                <NavMain title="Income" icon={Wallet} items={[
                    { title: 'Student payment', href: '/billings/student-bill', icon: CreditCard },
                    { title: 'Institute Other Bill', href: '/billings/institute-other-bill', icon: ReceiptText },
                ]} />
                <NavMain title="Expenditure" icon={Wallet} items={[
                    { title: 'Institute bill', href: '/billings/institute-billing', icon: ReceiptText },
                ]} />
                <NavMain title="Finance" icon={Wallet} items={[
                    { title: 'Student revenue', href: '/finance/student-revenue', icon: CreditCard },
                    { title: 'Institute expenditure', href: '/finance/institute-expenditure', icon: ReceiptText },
                ]} />
                <NavMain items={[
                    { title: 'Inventory', href: '/inventory', icon: Boxes },
                ]} />
                <NavMain title="Settings" variant="settings" icon={Settings} className="mt-auto" items={[
                    { title: 'Profile', href: '/settings/profile', icon: Users },
                    { title: 'Institute', href: '/settings/institute', icon: Building2 },
                    { title: 'User Control', href: '/settings/user-control', icon: Users },
                    { title: 'Finance Control', href: '/settings/finance-control', icon: Wallet },
                ]} />
            </SidebarContent>
        </Sidebar>
        </>
    );
}
