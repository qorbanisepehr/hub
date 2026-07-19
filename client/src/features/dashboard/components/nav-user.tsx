import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar";
import { UserMenu } from "@/components/shared/user-menu";
import { useAuth } from "@/features/auth/useAuth";

export function NavUser() {
    const { isMobile } = useSidebar();
    const { user } = useAuth();

    if (!user) return null;

    const initials = user.name
        ?.split(" ")
        .map((n: string) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <UserMenu
                    side={isMobile ? "bottom" : "right"}
                    trigger={
                        <SidebarMenuButton
                            size="lg"
                            className="aria-expanded:bg-muted"
                        />
                    }
                >
                    <Avatar className="size-8 rounded-lg after:rounded-lg">
                        {user.avatar_url && (
                            <AvatarImage
                                src={user.avatar_url}
                                alt={user.name}
                                className="rounded-lg"
                            />
                        )}
                        <AvatarFallback className="rounded-lg">
                            {initials}
                        </AvatarFallback>
                    </Avatar>
                    <div className="grid flex-1 text-start text-sm leading-tight">
                        <span className="truncate font-medium">
                            {user.name}
                        </span>
                        <span className="truncate text-xs text-foreground/70">
                            {user.active_role?.display_name ?? user.email}
                        </span>
                    </div>
                </UserMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
