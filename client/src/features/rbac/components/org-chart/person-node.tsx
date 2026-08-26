import { Handle, Position, type Node, type NodeProps } from "@xyflow/react";
import { IconMinus, IconPlus, IconUserOff } from "@tabler/icons-react";
import { UserAvatar } from "@/components/shared/user-avatar";
import { cn } from "@/lib/utils";
import { getUserDisplayName } from "@/lib/user-display";
import type { RoleChartRole } from "@/features/rbac/types";
import { Button } from "@/components/ui/button";
import { stopPropagation } from "./utils";
import { NodeToolbarActions } from "./node-toolbar-actions";

type PersonNodeData = {
    role: RoleChartRole;
    user?: RoleChartRole["users"][number];
    childCount: number;
    collapsed: boolean;
    hasChildren: boolean;
    onToggle?: (id: number) => void;
    onFocus?: (id: number) => void;
    onShowAncestors?: (id: number) => void;
    onShowSubtree?: (id: number) => void;
};

type PersonNode = Node<PersonNodeData, "personNode">;

/**
 * One node per person (کاربران view): same identity block as the sidebar
 * nav-user — avatar, name, and the role beneath it.
 */
export default function PersonNode({ data, selected }: NodeProps<PersonNode>) {
    const {
        role,
        user,
        collapsed,
        hasChildren,
        onToggle,
        onFocus,
        onShowAncestors,
        onShowSubtree,
    } = data;

    const displayName = user ? getUserDisplayName(user) : null;

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
                    "w-[210px] rounded-lg border bg-card shadow-sm transition-all duration-200",
                    selected && "ring-2 ring-ring shadow-md",
                    !selected && "hover:shadow-md",
                    !role.is_active && "opacity-60",
                )}
            >
                <div className="flex items-center justify-between p-2 pb-0">
                    <div className="flex items-center gap-2">
                        <NodeToolbarActions
                            roleId={role.id}
                            onShowAncestors={onShowAncestors}
                            onShowSubtree={onShowSubtree}
                            onFocus={onFocus}
                        />
                    </div>
                    <div className="flex items-center">
                        {/* <span
                            className={cn(
                                "size-2 rounded-full inline-block",
                                role.is_active ? "bg-primary" : "bg-secondary",
                            )}
                        ></span> */}
                    </div>
                </div>

                <div className="flex items-center gap-2.5 p-3">
                    {user ? (
                        <UserAvatar
                            name={displayName!}
                            avatarUrl={user.avatar_url}
                            size="default"
                        />
                    ) : (
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-dashed bg-muted/40 text-muted-foreground">
                            <IconUserOff className="size-4" />
                        </div>
                    )}
                    <div className="grid min-w-0 flex-1 text-start leading-tight">
                        <span className="truncate text-sm font-medium text-card-foreground">
                            {displayName ?? "بدون کاربر"}
                        </span>
                        <span className="truncate text-xs text-foreground/70">
                            {role.display_name}
                        </span>
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
