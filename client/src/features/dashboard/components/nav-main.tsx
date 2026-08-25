import type { ReactNode } from "react";
import { Fragment, useState } from "react";
import { Link, useRouterState } from "@tanstack/react-router";
import { IconChevronRight } from "@tabler/icons-react";
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from "@/components/ui/sidebar";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { NAV_ITEMS, type NavItem } from "@/features/dashboard/nav-items";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { cn } from "@/lib/utils";

function isActivePath(pathname: string, url: string): boolean {
    return pathname === url || pathname.startsWith(`${url}/`);
}

function usePathname(): string {
    return useRouterState({
        select: (state) => state.location.pathname,
    });
}

function NavLeaf({ item }: { item: NavItem }) {
    const pathname = usePathname();

    const link = (
        <SidebarMenuButton
            tooltip={item.title}
            isActive={item.url !== undefined && isActivePath(pathname, item.url)}
            render={<Link to={item.url!} search={item.search as never} />}
        >
            {item.icon}
            <span>{item.title}</span>
        </SidebarMenuButton>
    );

    return (
        <SidebarMenuItem>
            {item.permission ? (
                <PermissionGuard permission={item.permission}>
                    {link}
                </PermissionGuard>
            ) : (
                link
            )}
        </SidebarMenuItem>
    );
}

function NavGroup({ item }: { item: NavItem }) {
    const children = item.children ?? [];
    const pathname = usePathname();
    const [open, setOpen] = useState(() =>
        children.some(
            (child) => child.url !== undefined && isActivePath(pathname, child.url),
        ),
    );

    const menu = (
        <Collapsible open={open} onOpenChange={setOpen}>
            <SidebarMenuItem>
                <CollapsibleTrigger
                    render={<SidebarMenuButton tooltip={item.title} />}
                >
                    {item.icon}
                    <span>{item.title}</span>
                    {/* Points forward per direction (RTL mirrors), rotates
                        down while expanded — shadcn sidebar convention. */}
                    <IconChevronRight
                        className={cn(
                            "ms-auto transition-transform rtl:-scale-x-100",
                            open && "rotate-90",
                        )}
                    />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {children.map((child) => (
                            <SidebarMenuSubItem key={child.title}>
                                <SidebarMenuSubButton
                                    isActive={
                                        child.url !== undefined &&
                                        pathname === child.url
                                    }
                                    render={
                                        <Link
                                            to={child.url!}
                                            search={child.search as never}
                                        />
                                    }
                                >
                                    {child.icon}
                                    <span>{child.title}</span>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );

    return item.permission ? (
        <PermissionGuard permission={item.permission}>{menu}</PermissionGuard>
    ) : (
        menu
    );
}

export function NavMain() {
    const renderItem = (item: NavItem): ReactNode =>
        item.children ? <NavGroup item={item} /> : <NavLeaf item={item} />;

    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    {NAV_ITEMS.map((item) => (
                        <Fragment key={item.title}>{renderItem(item)}</Fragment>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}


