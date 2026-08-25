import * as React from "react";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";

/**
 * Shared user avatar: photo when available, initials fallback otherwise —
 * the same presentation rule as the sidebar user nav. Extra children
 * (e.g. an {@link AvatarBadge}) render inside the avatar.
 */
export function UserAvatar({
    name,
    avatarUrl,
    size = "sm",
    className,
    children,
}: {
    name: string;
    avatarUrl?: string | null;
    size?: "sm" | "default" | "lg";
    className?: string;
    children?: React.ReactNode;
}) {
    const initials = name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] ?? "")
        .join("")
        .toUpperCase();

    return (
        <Avatar size={size} className={cn("rounded-lg after:rounded-lg", className)}>
            {avatarUrl ? (
                <AvatarImage
                    src={avatarUrl}
                    alt={name}
                    className="rounded-lg"
                />
            ) : null}
            <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
            {children}
        </Avatar>
    );
}
