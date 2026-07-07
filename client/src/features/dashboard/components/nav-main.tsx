import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { IconDashboard, IconIdBadge2, IconSettings, IconUsers } from "@tabler/icons-react";
import { useAuth } from "@/features/auth/useAuth";
import { Link } from "@tanstack/react-router";

const items = [
    { title: "داشبورد", url: "/dashboard", icon: <IconDashboard /> },
    { title: "کارمندان", url: "/employees", icon: <IconIdBadge2 /> },
    { title: "کاربران", url: "#", icon: <IconUsers /> },
    { title: "تنظیمات", url: "#", icon: <IconSettings /> },
];

export function NavMain() {
    const { user } = useAuth();

    if (!user) return null;

    return (
        <SidebarGroup>
            <SidebarGroupContent className="flex flex-col gap-2">
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                tooltip={item.title}
                                render={<Link to={item.url} />}
                            >
                                {item.icon}
                                <span>{item.title}</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
