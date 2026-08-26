import { useCallback, useEffect, useMemo, useRef, useReducer } from "react";
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
import PersonNode from "./person-node";
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
const usersNodeTypes = { personNode: PersonNode };

type ChartUIState = {
    collapsedSet: Set<number>;
    subtreeRootId: number | null;
    layoutDirection: ChartDirection;
    selectedRoleId: number | null;
    modalOpen: boolean;
};

type ChartUIAction =
    | { type: "TOGGLE_COLLAPSE"; roleId: number; descendants: number[] }
    | { type: "SET_FOCUS"; collapsedSet: Set<number> }
    | { type: "SET_SUBTREE"; roleId: number | null }
    | { type: "EXPAND_ALL" }
    | { type: "COLLAPSE_ALL"; collapsedSet: Set<number> }
    | { type: "RESET_VIEW" }
    | { type: "SET_LAYOUT"; direction: ChartDirection }
    | { type: "SELECT_ROLE"; roleId: number }
    | { type: "CLOSE_MODAL" };

function chartUIReducer(state: ChartUIState, action: ChartUIAction): ChartUIState {
    switch (action.type) {
        case "TOGGLE_COLLAPSE": {
            const next = new Set(state.collapsedSet);
            if (next.has(action.roleId)) {
                next.delete(action.roleId);
            } else {
                next.add(action.roleId);
                for (const desc of action.descendants) {
                    next.delete(desc);
                }
            }
            return { ...state, collapsedSet: next };
        }
        case "SET_FOCUS":
            return { ...state, collapsedSet: action.collapsedSet, subtreeRootId: null };
        case "SET_SUBTREE":
            return { ...state, subtreeRootId: action.roleId };
        case "EXPAND_ALL":
            return { ...state, collapsedSet: new Set(), subtreeRootId: null };
        case "COLLAPSE_ALL":
            return { ...state, collapsedSet: action.collapsedSet, subtreeRootId: null };
        case "RESET_VIEW":
            return { ...state, collapsedSet: new Set(), subtreeRootId: null };
        case "SET_LAYOUT":
            return { ...state, layoutDirection: action.direction };
        case "SELECT_ROLE":
            return { ...state, selectedRoleId: action.roleId, modalOpen: true };
        case "CLOSE_MODAL":
            return { ...state, modalOpen: false };
    }
}

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
    const [uiState, dispatch] = useReducer(chartUIReducer, {
        collapsedSet: new Set(),
        subtreeRootId: null,
        layoutDirection: "TB" as ChartDirection,
        selectedRoleId: null,
        modalOpen: false,
    });
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
        const byType = (role: RoleChartRole) =>
            // The org chart represents the organizational structure; system
            // roles (admin, super admin) are not part of it.
            role.type === "organization";
        return roles.filter((role) => byUser(role) && byStatus(role) && byType(role));
    }, [roles, userFilter, statusFilter]);

    const chartRoles = filteredRoles;

    const selectedRole = useMemo(
        () => roles.find((role) => role.id === uiState.selectedRoleId) ?? null,
        [roles, uiState.selectedRoleId],
    );

    const handleLayoutChange = useCallback((direction: ChartDirection) => {
        dispatch({ type: "SET_LAYOUT", direction });
    }, []);

    const onToggle = useCallback(
        (roleId: number) => {
            if (togglingRef.current) {
                return;
            }
            togglingRef.current = true;
            const descendants = getDescendantIds(roleId, chartRoles);
            dispatch({ type: "TOGGLE_COLLAPSE", roleId, descendants });
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
            dispatch({ type: "SET_FOCUS", collapsedSet: getFocusCollapsedSet(roleId, chartRoles) });
        },
        [chartRoles],
    );

    const onFocusRef = useRef(onFocus);
    onFocusRef.current = onFocus;

    const onShowAncestors = useCallback(
        (roleId: number) => {
            dispatch({ type: "SET_FOCUS", collapsedSet: getAncestorCollapsedSet(roleId, chartRoles) });
        },
        [chartRoles],
    );

    const onShowAncestorsRef = useRef(onShowAncestors);
    onShowAncestorsRef.current = onShowAncestors;

    const onShowSubtree = useCallback((roleId: number) => {
        dispatch({ type: "SET_SUBTREE", roleId });
    }, []);

    const onShowSubtreeRef = useRef(onShowSubtree);
    onShowSubtreeRef.current = onShowSubtree;

    const onExpandAll = useCallback(() => {
        dispatch({ type: "EXPAND_ALL" });
    }, []);

    const onCollapseAll = useCallback(() => {
        dispatch({ type: "COLLAPSE_ALL", collapsedSet: getCollapseAllSet(chartRoles) });
    }, [chartRoles]);

    const onResetView = useCallback(() => {
        dispatch({ type: "RESET_VIEW" });
        fitView({ padding: 0.3, duration: 400 });
    }, [fitView]);

    useEffect(() => {
        if (
            uiState.subtreeRootId != null &&
            !chartRoles.some((role) => role.id === uiState.subtreeRootId)
        ) {
            dispatch({ type: "SET_SUBTREE", roleId: null });
        }
    }, [chartRoles, uiState.subtreeRootId]);

    const { nodes: rawNodes, edges: rawEdges } = useMemo(
        () =>
            buildNodesAndEdges(chartRoles, uiState.collapsedSet, uiState.subtreeRootId, {
                expandUsers: viewMode === "users",
            }),
        [chartRoles, uiState.collapsedSet, uiState.subtreeRootId, viewMode],
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
                uiState.layoutDirection,
                viewMode === "users" ? 180 : 150,
            ),
        [nodesWithToggle, rawEdges, uiState.layoutDirection, viewMode],
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
            const role = (node.data as { role?: RoleChartRole }).role;
            if (role) {
                dispatch({ type: "SELECT_ROLE", roleId: role.id });
            }
        },
        [],
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

                {uiState.subtreeRootId != null && (
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
                open={uiState.modalOpen}
                onOpenChange={(open) => {
                    if (!open) dispatch({ type: "CLOSE_MODAL" });
                }}
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
