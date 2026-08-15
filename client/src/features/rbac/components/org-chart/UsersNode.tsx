import { Handle, Position, type Node, type NodeProps } from "@xyflow/react";
import {
    IconArrowUp,
    IconChevronDown,
    IconCrosshair,
    IconGitBranch,
    IconMinimize,
    IconMinus,
    IconPlus,
    IconUserOff,
} from "@tabler/icons-react";
import {
    Avatar,
    AvatarFallback,
    AvatarGroup,
    AvatarGroupCount,
    AvatarImage,
} from "@/components/ui/avatar";
import { cn } from "@/lib/utils";
import { getUserDisplayName } from "@/lib/user-display";
import type { RoleChartRole } from "@/features/rbac/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

const MAX_AVATARS = 4;

type UsersNodeData = {
    role: RoleChartRole;
    childCount: number;
    collapsed: boolean;
    hasChildren: boolean;
    onToggle?: (id: number) => void;
    onFocus?: (id: number) => void;
    onShowAncestors?: (id: number) => void;
    onShowSubtree?: (id: number) => void;
};

type UsersNode = Node<UsersNodeData, "customNode">;

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return parts
        .slice(0, 2)
        .map((part) => part[0] ?? "")
        .join("");
}

function stopPropagation(handler?: (id: number) => void, id?: number) {
    return (event: React.MouseEvent) => {
        event.stopPropagation();
        event.preventDefault();
        if (handler && id != null) {
            handler(id);
        }
    };
}

export default function UsersNode({ data, selected }: NodeProps<UsersNode>) {
    const {
        role,
        childCount,
        collapsed,
        hasChildren,
        onToggle,
        onFocus,
        onShowAncestors,
        onShowSubtree,
    } = data;

    const users = role.users ?? [];
    const visibleUsers = users.slice(0, MAX_AVATARS);
    const hiddenCount = users.length - visibleUsers.length;

    return (
        <>
            <Handle
                type="target"
                position={Position.Top}
                style={{
                    background: "transparent",
                    border: "none",
                    width: 6,
                    height: 6,
                }}
            />
            <div
                dir="rtl"
                className={cn(
                    "w-[210px] rounded-lg border bg-card shadow-sm transition-all duration-200 relative",
                    selected && "ring-2 ring-ring shadow-md",
                    !selected && "hover:shadow-md",
                    !role.is_active && "opacity-60",
                )}
            >
                <div className="flex items-center gap-0.5 justify-between p-2">
                    <div className="flex items-center gap-0.5">
                        <button
                            onClick={stopPropagation(onShowAncestors, role.id)}
                            className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                            title="نمایش مسیر تا نقش ریشه"
                        >
                            <IconArrowUp className="size-3" />
                        </button>
                        <button
                            onClick={stopPropagation(onShowSubtree, role.id)}
                            className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                            title="نمایش زیرمجموعه به عنوان ریشه"
                        >
                            <IconGitBranch className="size-3" />
                        </button>
                        <button
                            onClick={stopPropagation(onFocus, role.id)}
                            className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                            title="تمرکز روی این نقش"
                        >
                            <IconCrosshair className="size-3" />
                        </button>
                    </div>

                    <div>
                        {/* <span
                            className={cn(
                                "inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold",
                                role.is_active
                                    ? "border-primary/20 bg-primary/10 text-primary"
                                    : "border-border bg-muted text-muted-foreground",
                            )}
                        >
                            {role.is_active ? "فعال" : "غیرفعال"}
                        </span> */}
                        <Badge
                            variant={role.is_active ? "default" : "secondary"}
                        >
                            {role.is_active ? "فعال" : "غیرفعال"}
                        </Badge>
                    </div>
                </div>

                <div className="px-3 pb-2 space-y-2.5">
                    <div className="border-t pt-2">
                        {users.length > 0 ? (
                            <div className="flex items-center gap-2">
                                <AvatarGroup>
                                    {visibleUsers.map((user) => (
                                        <Avatar key={user.id} size="sm">
                                            <AvatarImage
                                                src={
                                                    user.avatar_url ?? undefined
                                                }
                                                alt={getUserDisplayName(user)}
                                            />
                                            <AvatarFallback>
                                                {getInitials(getUserDisplayName(user))}
                                            </AvatarFallback>
                                        </Avatar>
                                    ))}
                                    {hiddenCount > 0 && (
                                        <AvatarGroupCount>
                                            +{hiddenCount}
                                        </AvatarGroupCount>
                                    )}
                                </AvatarGroup>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-medium leading-tight text-card-foreground">
                                        {visibleUsers.length === 1
                                            ? getUserDisplayName(visibleUsers[0])
                                            : `${users.length} کاربر`}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-center gap-2">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full border border-dashed bg-muted/40 text-muted-foreground">
                                    <IconUserOff className="size-4" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="truncate text-xs font-medium leading-tight text-muted-foreground">
                                        بدون کاربر
                                    </p>
                                </div>
                            </div>
                        )}
                        <p className="truncate text-sm font-semibold leading-tight text-card-foreground">
                            {role.display_name}
                        </p>
                        {/* <p
                            dir="ltr"
                            className="mt-0.5 truncate text-right text-[11px] text-muted-foreground"
                        >
                            {role.name}
                        </p> */}
                    </div>
                </div>

                {hasChildren && (
                    <div className="absolute -bottom-5 left-0 right-0 flex cursor-pointer items-center justify-center">
                        <Button
                            size="icon-xs"
                            variant={collapsed ? "outline" : "default"}
                            onClick={stopPropagation(onToggle, role.id)}
                            className="cursor-pointer"
                        >
                            {collapsed ? (
                                <IconPlus className="size-3.5 transition-transform duration-200" />
                            ) : (
                                <IconMinus className="size-3.5 transition-transform duration-200" />
                            )}
                        </Button>
                        {/* <span>
                            {collapsed
                                ? `نمایش زیرمجموعه (${childCount})`
                                : "جمع‌کردن"}
                        </span> */}
                    </div>
                )}
            </div>
            <Handle
                type="source"
                position={Position.Bottom}
                style={{
                    background: "transparent",
                    border: "none",
                    width: 6,
                    height: 6,
                }}
            />
        </>
    );
}
