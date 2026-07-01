import * as React from "react";

import { NavMain } from "@/features/dashboard/components/nav-main";
import { NavUser } from "@/features/dashboard/components/nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { Logo, LogoType } from "@/components/shared/logo";
import { Link } from "@tanstack/react-router";

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            render={
                                <Link
                                    to="/"
                                    className="flex justify-between items-center px-2 py-3 h-auto rounded-xl"
                                />
                            }
                        >
                            <div className="space-y-1 group-data-[collapsible=icon]:hidden transition-all">
                                <LogoType className="w-16!" />
                                <span className="text-xs text-primary/50">
                                    خدمات فنی و مهندسی
                                </span>
                            </div>
                            <Logo className="size-9! group-data-[collapsible=icon]:size-6!" />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
