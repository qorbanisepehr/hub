import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { IconDashboard, IconIdBadge2, IconSettings, IconMasksTheater, IconUsers } from "@tabler/icons-react";
import { Link } from "@tanstack/react-router";
import { PermissionGuard } from "@/features/auth/components/permission-guard";

const items = [
    { title: "داشبورد", url: "/dashboard", icon: <IconDashboard /> },
    { title: "کارمندان", url: "/employees", icon: <IconIdBadge2 />, permission: ["employee.view_own", "employee.view_all"] as const },
    { title: "کاربران", url: "/users", icon: <IconUsers />, permission: "user.view" as const },
    { title: "نقش‌ها", url: "/roles", icon: <IconMasksTheater />, permission: "role.view" as const },
    { title: "تنظیمات", url: "/settings", icon: <IconSettings />, permission: ["permission-category.view", "permission-category.manage"] as const },
];

export function NavMain() {
    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            {"permission" in item ? (
                                <PermissionGuard permission={item.permission}>
                                    <SidebarMenuButton
                                        tooltip={item.title}
                                        render={<Link to={item.url} />}
                                    >
                                        {item.icon}
                                        <span>{item.title}</span>
                                    </SidebarMenuButton>
                                </PermissionGuard>
                            ) : (
                                <SidebarMenuButton
                                    tooltip={item.title}
                                    render={<Link to={item.url} />}
                                >
                                    {item.icon}
                                    <span>{item.title}</span>
                                </SidebarMenuButton>
                            )}
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
