import { type ReactElement, type ReactNode } from "react";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuth } from "@/features/auth/useAuth";
import { useNavigate } from "@tanstack/react-router";
import { IconLayoutDashboard, IconLogout, IconUserCircle } from "@tabler/icons-react";

type UserMenuProps = {
    align?: "start" | "end";
    side?: "top" | "bottom" | "left" | "right";
    sideOffset?: number;
    trigger?: ReactElement;
    children?: ReactNode;
};

export function UserMenu({
    align = "end",
    side = "bottom",
    sideOffset = 4,
    trigger = <Button variant="ghost" className="size-8 rounded-lg p-0" />,
    children,
}: UserMenuProps) {
    const { user, logout, isLoggingOut } = useAuth();
    const navigate = useNavigate();

    if (!user) return null;

    const initials = user.name
        ?.split(" ")
        .map((n: string) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    const handleLogout = async () => {
        try {
            await logout();
            navigate({ to: "/login" });
        } catch {
            // handled by useAuth
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={trigger}>
                {children ?? (
                    <Avatar className="size-8 rounded-lg after:rounded-lg">
                        <AvatarFallback className="text-xs rounded-lg after:rounded-lg">
                            {initials}
                        </AvatarFallback>
                    </Avatar>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align={align}
                side={side}
                sideOffset={sideOffset}
                className="min-w-48"
            >
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="font-normal">
                        <div className="flex flex-col gap-1">
                            <p className="text-sm font-medium">{user.name}</p>
                            <p className="text-xs text-muted-foreground">{user.email}</p>
                        </div>
                    </DropdownMenuLabel>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem render={<a href="/dashboard" />}>
                        <IconLayoutDashboard className="size-4" />
                        داشبورد
                    </DropdownMenuItem>
                    <DropdownMenuItem render={<a href="/profile" />}>
                        <IconUserCircle className="size-4" />
                        حساب کاربری
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem onClick={handleLogout} disabled={isLoggingOut}>
                        <IconLogout className="size-4" />
                        {isLoggingOut ? "در حال خروج..." : "خروج"}
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
