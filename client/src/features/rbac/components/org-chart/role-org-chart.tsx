import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
    Background,
    BackgroundVariant,
    MiniMap,
    ReactFlow,
    ReactFlowProvider,
    SelectionMode,
    useEdgesState,
    useNodesState,
    useReactFlow,
    type NodeMouseHandler,
} from "@xyflow/react";
import "@xyflow/react/dist/style.css";
import {
    IconArrowsHorizontal,
    IconArrowsMaximize,
    IconArrowsVertical,
    IconArrowBackUp,
    IconFold,
    IconMasksTheater,
    IconMaximize,
    IconMinimize,
    IconViewportShort,
    IconViewportTall,
    IconZoomScan,
} from "@tabler/icons-react";
import CustomNode from "./custom-node";
import UsersNode from "./users-node";
import { RoleDetailModal } from "./role-detail-modal";
import {
    buildNodesAndEdges,
    getAncestorCollapsedSet,
    getCollapseAllSet,
    getDescendantIds,
    getFocusCollapsedSet,
    layoutNodes,
    type ChartDirection,
} from "./layout-utils";
import type {
    ChartStatusFilter,
    ChartUserFilter,
    ChartViewMode,
    RoleChartRole,
} from "@/features/rbac/types";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/layout";
import { ErrorSection } from "@/components/layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useFullscreen } from "@/hooks/use-full-screen";

const nodeTypes = { customNode: CustomNode };
const usersNodeTypes = { customNode: UsersNode };

function RoleOrgChartInner({
    roles,
    isLoading,
    isError,
    onRetry,
    viewMode,
    userFilter,
    statusFilter,
}: {
    roles: RoleChartRole[];
    isLoading: boolean;
    isError: boolean;
    onRetry: () => void;
    viewMode: ChartViewMode;
    userFilter: ChartUserFilter;
    statusFilter: ChartStatusFilter;
}) {
    const [collapsedSet, setCollapsedSet] = useState<Set<number>>(new Set());
    const [subtreeRootId, setSubtreeRootId] = useState<number | null>(null);
    const [layoutDirection, setLayoutDirection] =
        useState<ChartDirection>("TB");
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const { fitView } = useReactFlow();
    const togglingRef = useRef(false);
    const flowRef = useRef<HTMLDivElement>(null);
    const { isFullscreen, toggle: toggleFullscreen } = useFullscreen(
        flowRef,
        (active) => {
            // React Flow بعد از تغییر اندازه کانتینر نیاز به یک fitView مجدد دارد
            setTimeout(
                () => fitView({ padding: 0.3, duration: 300 }),
                active ? 200 : 100,
            );
        },
    );

    const filteredRoles = useMemo(() => {
        const byUser = (role: RoleChartRole) => {
            if (userFilter === "with") {
                return role.user_count > 0;
            }
            if (userFilter === "without") {
                return role.user_count === 0;
            }
            return true;
        };
        const byStatus = (role: RoleChartRole) => {
            if (statusFilter === "active") {
                return role.is_active;
            }
            if (statusFilter === "inactive") {
                return !role.is_active;
            }
            return true;
        };
        return roles.filter((role) => byUser(role) && byStatus(role));
    }, [roles, userFilter, statusFilter]);

    const chartRoles = filteredRoles;

    const selectedRole = useMemo(
        () => roles.find((role) => role.id === selectedRoleId) ?? null,
        [roles, selectedRoleId],
    );

    const handleLayoutChange = useCallback((direction: ChartDirection) => {
        setLayoutDirection(direction);
    }, []);

    const onToggle = useCallback(
        (roleId: number) => {
            if (togglingRef.current) {
                return;
            }
            togglingRef.current = true;
            setCollapsedSet((prev) => {
                const next = new Set(prev);
                if (next.has(roleId)) {
                    next.delete(roleId);
                } else {
                    const descendants = getDescendantIds(roleId, chartRoles);
                    next.add(roleId);
                    for (const descendant of descendants) {
                        next.delete(descendant);
                    }
                }
                return next;
            });
            requestAnimationFrame(() => {
                togglingRef.current = false;
            });
        },
        [chartRoles],
    );

    const onToggleRef = useRef(onToggle);
    onToggleRef.current = onToggle;

    const onFocus = useCallback(
        (roleId: number) => {
            setSubtreeRootId(null);
            setCollapsedSet(getFocusCollapsedSet(roleId, chartRoles));
        },
        [chartRoles],
    );

    const onFocusRef = useRef(onFocus);
    onFocusRef.current = onFocus;

    const onShowAncestors = useCallback(
        (roleId: number) => {
            setSubtreeRootId(null);
            setCollapsedSet(getAncestorCollapsedSet(roleId, chartRoles));
        },
        [chartRoles],
    );

    const onShowAncestorsRef = useRef(onShowAncestors);
    onShowAncestorsRef.current = onShowAncestors;

    const onShowSubtree = useCallback((roleId: number) => {
        setSubtreeRootId(roleId);
    }, []);

    const onShowSubtreeRef = useRef(onShowSubtree);
    onShowSubtreeRef.current = onShowSubtree;

    const onExpandAll = useCallback(() => {
        setSubtreeRootId(null);
        setCollapsedSet(new Set());
    }, []);

    const onCollapseAll = useCallback(() => {
        setSubtreeRootId(null);
        setCollapsedSet(getCollapseAllSet(chartRoles));
    }, [chartRoles]);

    const onResetView = useCallback(() => {
        setSubtreeRootId(null);
        setCollapsedSet(new Set());
        fitView({ padding: 0.3, duration: 400 });
    }, [fitView]);

    useEffect(() => {
        if (
            subtreeRootId != null &&
            !chartRoles.some((role) => role.id === subtreeRootId)
        ) {
            setSubtreeRootId(null);
        }
    }, [chartRoles, subtreeRootId]);

    const { nodes: rawNodes, edges: rawEdges } = useMemo(
        () => buildNodesAndEdges(chartRoles, collapsedSet, subtreeRootId),
        [chartRoles, collapsedSet, subtreeRootId],
    );

    const nodesWithToggle = useMemo(
        () =>
            rawNodes.map((node) => ({
                ...node,
                data: {
                    ...node.data,
                    onToggle: onToggleRef.current,
                    onFocus: onFocusRef.current,
                    onShowAncestors: onShowAncestorsRef.current,
                    onShowSubtree: onShowSubtreeRef.current,
                },
            })),
        [rawNodes],
    );

    const layoutedNodes = useMemo(
        () =>
            layoutNodes(
                nodesWithToggle,
                rawEdges,
                layoutDirection,
                viewMode === "users" ? 180 : 150,
            ),
        [nodesWithToggle, rawEdges, layoutDirection, viewMode],
    );

    const [nodes, setNodes, onNodesChange] = useNodesState(layoutedNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(rawEdges);

    useEffect(() => {
        setNodes(layoutedNodes);
        setEdges(rawEdges);
    }, [layoutedNodes, rawEdges, setNodes, setEdges]);

    useEffect(() => {
        if (nodes.length > 0) {
            const timer = setTimeout(
                () => fitView({ padding: 0.3, duration: 300 }),
                100,
            );
            return () => clearTimeout(timer);
        }
    }, [nodes.length, viewMode, userFilter, statusFilter, fitView]);

    const onNodeClick: NodeMouseHandler = useCallback(
        (_event, node) => {
            const role = roles.find((item) => item.id === Number(node.id));
            if (role) {
                setSelectedRoleId(role.id);
                setModalOpen(true);
            }
        },
        [roles],
    );

    if (isLoading) {
        return (
            <div className="flex size-full items-center justify-center">
                <Skeleton className="size-full rounded-xl" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex size-full items-center justify-center">
                <ErrorSection onRetry={onRetry} />
            </div>
        );
    }

    if (chartRoles.length === 0) {
        return (
            <div className="flex size-full items-center justify-center">
                <EmptyState
                    icon={IconMasksTheater}
                    message="نقشی برای نمایش وجود ندارد"
                />
            </div>
        );
    }

    return (
        <div
            ref={flowRef}
            dir="rtl"
            className="relative size-full overflow-hidden rounded-xl border bg-background"
        >
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onNodeClick={onNodeClick}
                nodeTypes={viewMode === "users" ? usersNodeTypes : nodeTypes}
                fitView={false}
                selectionMode={SelectionMode.Partial}
                minZoom={0.2}
                maxZoom={2}
                panOnDrag
                zoomOnScroll
                selectNodesOnDrag
                deleteKeyCode={null}
            >
                <Background
                    variant={BackgroundVariant.Dots}
                    gap={24}
                    size={1}
                    color="var(--border)"
                />
                <div className="absolute bottom-4 left-2 z-10 flex flex-col gap-1.5">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={toggleFullscreen}
                        title={
                            isFullscreen
                                ? "خروج از تمام‌صفحه"
                                : "نمایش تمام‌صفحه"
                        }
                    >
                        {isFullscreen ? (
                            <IconMinimize className="size-4" />
                        ) : (
                            <IconMaximize className="size-4" />
                        )}
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => handleLayoutChange("TB")}
                        title="چیدمان عمودی"
                    >
                        <IconArrowsVertical className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => handleLayoutChange("LR")}
                        title="چیدمان افقی"
                    >
                        <IconArrowsHorizontal className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={onExpandAll}
                        title="بازکردن همه"
                    >
                        {/* <IconArrowsMaximize className="size-4" /> */}
                        <IconViewportTall className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={onCollapseAll}
                        title="جمع‌کردن همه"
                    >
                        {/* <IconFold className="size-4" /> */}
                        <IconViewportShort className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={onResetView}
                        title="بازآرایی نمای کامل"
                    >
                        <IconZoomScan className="size-4" />
                    </Button>
                </div>
                <MiniMap
                    nodeStrokeColor="var(--border)"
                    nodeColor="var(--muted)"
                    nodeBorderRadius={4}
                    maskColor="var(--color-minimap-mask)"
                    className="rounded-lg! border! border-border! shadow-sm! cursor-move bg-background! [&>svg]:p-0.5!"
                    pannable={true}
                    offsetScale={1}
                />

                {subtreeRootId != null && (
                    <div className="absolute top-4 left-4 z-10">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onResetView}
                            className="shadow-sm"
                        >
                            <IconArrowBackUp className="size-3.5" />
                            <span>بازگشت به نمای کامل</span>
                        </Button>
                    </div>
                )}

                <div className="absolute top-4 right-4 z-10 rounded-lg border bg-card/90 p-2.5 text-xs shadow-sm backdrop-blur">
                    <div className="space-y-1">
                        <div className="flex items-center justify-between gap-2">
                            <div className="h-0.5 w-6 rounded bg-foreground opacity-45" />
                            <span className="text-muted-foreground">
                                روابط مستقیم
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <div
                                className="w-6 border-t border-dashed"
                                style={{ borderColor: "var(--primary)" }}
                            />
                            <span className="text-muted-foreground">
                                روابط غیرمستقیم
                            </span>
                        </div>
                    </div>
                </div>
            </ReactFlow>

            <RoleDetailModal
                role={selectedRole}
                roles={roles}
                open={modalOpen}
                onOpenChange={setModalOpen}
            />
        </div>
    );
}

export function RoleOrgChart({
    roles,
    isLoading,
    isError,
    onRetry,
    viewMode,
    userFilter,
    statusFilter,
}: {
    roles: RoleChartRole[];
    isLoading: boolean;
    isError: boolean;
    onRetry: () => void;
    viewMode: ChartViewMode;
    userFilter: ChartUserFilter;
    statusFilter: ChartStatusFilter;
}) {
    return (
        <ReactFlowProvider>
            <RoleOrgChartInner
                roles={roles}
                isLoading={isLoading}
                isError={isError}
                onRetry={onRetry}
                viewMode={viewMode}
                userFilter={userFilter}
                statusFilter={statusFilter}
            />
        </ReactFlowProvider>
    );
}
