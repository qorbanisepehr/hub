import { Handle, Position, type Node, type NodeProps } from "@xyflow/react";
import {
    IconArrowUp,
    IconChevronDown,
    IconCrosshair,
    IconGitBranch,
    IconMasksTheater,
    IconUsers,
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

const MAX_AVATARS = 4;

type CustomNodeData = {
    role: RoleChartRole;
    childCount: number;
    collapsed: boolean;
    hasChildren: boolean;
    onToggle?: (id: number) => void;
    onFocus?: (id: number) => void;
    onShowAncestors?: (id: number) => void;
    onShowSubtree?: (id: number) => void;
};

type RoleNode = Node<CustomNodeData, "customNode">;

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

export default function CustomNode({ data, selected }: NodeProps<RoleNode>) {
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

    const matrixManagerCount = role.matrix_manager_roles?.length ?? 0;
    const users = role.users ?? [];
    const visibleUsers = users.slice(0, MAX_AVATARS);
    const hiddenCount = users.length - visibleUsers.length;

    return (
        <>
            <Handle
                type="target"
                position={Position.Top}
                style={{ background: "transparent", border: "none", width: 6, height: 6 }}
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
                <div className="absolute top-1.5 left-1.5 z-10 flex items-center gap-0.5">
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

                <div className="p-3 space-y-2.5">
                    <div className="flex items-center gap-2.5">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-border">
                            <IconMasksTheater className="size-4.5" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="truncate text-sm font-semibold leading-tight text-card-foreground">
                                {role.display_name}
                            </p>
                            <p dir="ltr" className="mt-0.5 truncate text-right text-[11px] text-muted-foreground">
                                {role.name}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <span
                            className={cn(
                                "inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold",
                                role.is_active
                                    ? "border-primary/20 bg-primary/10 text-primary"
                                    : "border-border bg-muted text-muted-foreground",
                            )}
                        >
                            {role.is_active ? "فعال" : "غیرفعال"}
                        </span>
                        <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            <span className="inline-flex items-center gap-1">
                                <IconUsers className="size-3" />
                                <span className="font-medium">{role.user_count}</span>
                            </span>
                            {matrixManagerCount > 0 && (
                                <span className="inline-flex items-center gap-1 rounded border border-dashed px-1 py-0.5 text-[10px]">
                                    {matrixManagerCount} مدیر ماتریسی
                                </span>
                            )}
                        </div>
                    </div>

                    {users.length > 0 && (
                        <div className="border-t pt-2.5">
                            <AvatarGroup>
                                {visibleUsers.map((user) => (
                                    <Avatar key={user.id} size="sm">
                                        <AvatarImage
                                            src={user.avatar_url ?? undefined}
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
                        </div>
                    )}
                </div>

                {hasChildren && (
                    <div
                        onClick={stopPropagation(onToggle, role.id)}
                        className={cn(
                            "flex cursor-pointer items-center justify-center gap-1 border-t py-1.5 text-[10px] font-medium text-muted-foreground transition-colors hover:bg-muted/30 hover:text-foreground",
                            collapsed && "bg-muted/20",
                        )}
                    >
                        <IconChevronDown
                            className={cn(
                                "size-3.5 transition-transform duration-200",
                                collapsed && "rotate-180",
                            )}
                        />
                        <span>{collapsed ? `نمایش زیرمجموعه (${childCount})` : "جمع‌کردن"}</span>
                    </div>
                )}
            </div>
            <Handle
                type="source"
                position={Position.Bottom}
                style={{ background: "transparent", border: "none", width: 6, height: 6 }}
            />
        </>
    );
}
