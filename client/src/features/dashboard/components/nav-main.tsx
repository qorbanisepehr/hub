import type { ReactNode } from "react";
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { IconDashboard, IconIdBadge2, IconSettings, IconMasksTheater, IconUsers, IconHierarchy2, IconFileCv, IconPalette } from "@tabler/icons-react";
import { Link } from "@tanstack/react-router";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";

interface NavItem {
    title: string;
    url: string;
    icon: ReactNode;
    permission?: string | string[];
    search?: Record<string, unknown>;
}

const items: NavItem[] = [
    { title: "داشبورد", url: "/dashboard", icon: <IconDashboard /> },
    { title: "کارمندان", url: "/employees", icon: <IconIdBadge2 />, permission: [PERMISSIONS.EMPLOYEE_VIEW_OWN, PERMISSIONS.EMPLOYEE_VIEW_ALL] },
    { title: "کاربران", url: "/users", icon: <IconUsers />, permission: PERMISSIONS.USER_VIEW },
    { title: "نقش‌ها", url: "/roles", icon: <IconMasksTheater />, permission: PERMISSIONS.ROLE_VIEW },
    { title: "نقشه سازمانی", url: "/roles/chart", icon: <IconHierarchy2 />, permission: PERMISSIONS.ROLE_VIEW },
    { title: "بانک رزومه", url: "/cvs", icon: <IconFileCv />, permission: PERMISSIONS.CV_VIEW },
    { title: "تنظیمات", url: "/settings", icon: <IconSettings />, permission: [PERMISSIONS.DOCUMENT_CATEGORY_VIEW, PERMISSIONS.DOCUMENT_CATEGORY_MANAGE] },
    { title: "برندینگ", url: "/settings", icon: <IconPalette />, permission: [PERMISSIONS.BRANDING_VIEW, PERMISSIONS.BRANDING_MANAGE], search: { tab: "branding" } },
];

export function NavMain() {
    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    {items.map((item) => {
                        const link = (
                            <SidebarMenuButton
                                tooltip={item.title}
                                render={<Link to={item.url} search={item.search as never} />}
                            >
                                {item.icon}
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                        );

                        return (
                            <SidebarMenuItem key={item.title}>
                                {item.permission ? (
                                    <PermissionGuard permission={item.permission}>
                                        {link}
                                    </PermissionGuard>
                                ) : (
                                    link
                                )}
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
