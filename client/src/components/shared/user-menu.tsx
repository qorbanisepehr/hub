import { type ReactElement, type ReactNode } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
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
import { authKeys } from "@/lib/query-keys";
import { switchActiveRole } from "@/features/auth/api";
import { getApiError } from "@/lib/error-utils";
import { getUserDisplayName } from "@/lib/user-display";
import { Link, useNavigate } from "@tanstack/react-router";
import {
    IconMasksTheater,
    IconCheck,
    IconLayoutDashboard,
    IconLogout,
    IconUserCircle,
} from "@tabler/icons-react";

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
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const roleMutation = useMutation({
        mutationFn: (roleId: number) => switchActiveRole(roleId),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: authKeys.me() });
            toast.success("نقش فعال تغییر کرد.");
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    if (!user) return null;

    const displayName = getUserDisplayName(user);

    const initials = displayName
        ?.split(" ")
        .map((n: string) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    const roles = user.roles ?? [];

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
                        {user.avatar_url && (
                            <AvatarImage
                                src={user.avatar_url}
                                alt={displayName}
                                className="rounded-lg"
                            />
                        )}
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
                className="min-w-56"
            >
                <DropdownMenuGroup>
                    <DropdownMenuLabel className="font-normal">
                        <div className="flex flex-col gap-1">
                            <p className="text-sm font-medium">{displayName}</p>
                            <p className="text-xs text-muted-foreground">
                                {user.active_role?.display_name ?? user.email}
                            </p>
                        </div>
                    </DropdownMenuLabel>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem render={<Link to="/dashboard" />}>
                        <IconLayoutDashboard className="size-4" />
                        داشبورد
                    </DropdownMenuItem>
                    <DropdownMenuItem render={<Link to="/profile" />}>
                        <IconUserCircle className="size-4" />
                        حساب کاربری
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                {roles.length > 1 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuLabel className="flex items-center gap-2 text-xs text-muted-foreground">
                                <IconMasksTheater className="size-3.5" />
                                نقش فعال
                            </DropdownMenuLabel>
                            {roles.map((role) => {
                                const isActive = user.active_role?.id === role.id;
                                return (
                                    <DropdownMenuItem
                                        key={role.id}
                                        disabled={isActive || roleMutation.isPending}
                                        onClick={() => roleMutation.mutate(role.id)}
                                        className="gap-2"
                                    >
                                        <IconCheck
                                            className={`size-4 shrink-0 ${
                                                isActive ? "text-primary" : "text-transparent"
                                            }`}
                                        />
                                        {role.display_name}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuGroup>
                    </>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        onClick={handleLogout}
                        disabled={isLoggingOut}
                    >
                        <IconLogout className="size-4" />
                        {isLoggingOut ? "در حال خروج..." : "خروج"}
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
