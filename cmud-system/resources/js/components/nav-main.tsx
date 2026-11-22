import { Link } from '@inertiajs/react';
import { ChevronDown, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';
import { cn } from '@/lib/utils';

export function NavMain({ items, title, icon: Icon, className = '', variant = 'default' }: { items: NavItem[]; title?: string; icon?: LucideIcon; className?: string; variant?: 'default' | 'settings' }) {
    const { isCurrentUrl, currentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const [toggle, setToggle] = useState<{ url: string; open: boolean } | null>(null);
    const settings = variant === 'settings';
    const active = items.some((item) => isCurrentUrl(item.href));
    const iconOnly = state === 'collapsed' && !isMobile;
    const open = iconOnly || (toggle?.url === currentUrl ? toggle.open : active);

    if (!title) {
        return (
            <SidebarGroup className={cn('px-2 py-0', className)}>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={isCurrentUrl(item.href)} tooltip={{ children: item.title }} className="h-9 font-medium">
                                <Link href={item.href} prefetch aria-current={isCurrentUrl(item.href) ? 'page' : undefined}>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        );
    }

    return (
        <Collapsible asChild open={open} onOpenChange={(nextOpen) => setToggle({ url: currentUrl, open: nextOpen })}>
            <SidebarGroup className={cn("px-2 py-0", settings && "p-2", className)}>
                {settings ? (
                    <CollapsibleTrigger className="group/category flex w-full items-center gap-3 rounded-lg px-1 py-2 text-left text-[#17375C] transition-colors hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-teal-600 group-data-[collapsible=icon]:hidden dark:text-slate-100 dark:hover:text-teal-300">
                        <span className="grid size-9 shrink-0 place-items-center ">{Icon && <Icon size={22} aria-hidden="true" />}</span>
                        <span className="flex-1 text-sm font-semibold">{title}</span>
                        <ChevronDown size={16} aria-hidden="true" className="text-slate-500 transition-transform duration-200 group-data-[state=closed]/category:-rotate-90" />
                    </CollapsibleTrigger>
                ) : (
                    <SidebarGroupLabel asChild>
                        <CollapsibleTrigger className="group/category w-full cursor-pointer text-left hover:bg-slate-200/70 focus-visible:ring-2 focus-visible:ring-teal-400 dark:hover:bg-slate-700">
                            {Icon && <Icon aria-hidden="true" />}
                            <span className="flex-1">{title}</span>
                            <ChevronDown aria-hidden="true" className="transition-transform duration-200 group-data-[state=closed]/category:-rotate-90" />
                        </CollapsibleTrigger>
                    </SidebarGroupLabel>
                )}
                <CollapsibleContent>
                    <div className="mt-2 ml-4 border-l border-slate-300 pl-2 group-data-[collapsible=icon]:mt-0 group-data-[collapsible=icon]:ml-0 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:pl-0">
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={isCurrentUrl(item.href)} tooltip={{ children: item.title }} className={cn(settings && "bg-transparent hover:bg-transparent active:bg-transparent data-[active=true]:bg-transparent data-[state=open]:hover:bg-transparent hover:text-teal-700 data-[active=true]:text-teal-700 dark:hover:text-teal-300 dark:data-[active=true]:text-teal-300")}>
                                        <Link href={item.href} prefetch aria-current={isCurrentUrl(item.href) ? 'page' : undefined}>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </div>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
