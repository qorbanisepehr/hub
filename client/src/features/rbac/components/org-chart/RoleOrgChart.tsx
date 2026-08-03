import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
    Background,
    BackgroundVariant,
    ControlButton,
    Controls,
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
} from "@tabler/icons-react";
import CustomNode from "./CustomNode";
import { RoleDetailModal } from "./RoleDetailModal";
import {
    buildNodesAndEdges,
    getAncestorCollapsedSet,
    getCollapseAllSet,
    getDescendantIds,
    getFocusCollapsedSet,
    layoutNodes,
    type ChartDirection,
} from "./layoutUtils";
import type { RoleChartRole } from "@/features/rbac/types";
import { EmptyState } from "@/components/shared/empty-state";
import { ErrorSection } from "@/components/shared/error-section";
import { Skeleton } from "@/components/ui/skeleton";

const nodeTypes = { customNode: CustomNode };

function RoleOrgChartInner({
    roles,
    isLoading,
    isError,
    onRetry,
}: {
    roles: RoleChartRole[];
    isLoading: boolean;
    isError: boolean;
    onRetry: () => void;
}) {
    const [collapsedSet, setCollapsedSet] = useState<Set<number>>(new Set());
    const [subtreeRootId, setSubtreeRootId] = useState<number | null>(null);
    const [layoutDirection, setLayoutDirection] = useState<ChartDirection>("TB");
    const [selectedRole, setSelectedRole] = useState<RoleChartRole | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const { fitView } = useReactFlow();
    const togglingRef = useRef(false);

    const handleLayoutChange = useCallback((direction: ChartDirection) => {
        setLayoutDirection(direction);
    }, []);

    const onToggle = useCallback((roleId: number) => {
        if (togglingRef.current) {
            return;
        }
        togglingRef.current = true;
        setCollapsedSet((prev) => {
            const next = new Set(prev);
            if (next.has(roleId)) {
                next.delete(roleId);
            } else {
                const descendants = getDescendantIds(roleId, roles);
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
    }, [roles]);

    const onToggleRef = useRef(onToggle);
    onToggleRef.current = onToggle;

    const onFocus = useCallback((roleId: number) => {
        setSubtreeRootId(null);
        setCollapsedSet(getFocusCollapsedSet(roleId, roles));
    }, [roles]);

    const onFocusRef = useRef(onFocus);
    onFocusRef.current = onFocus;

    const onShowAncestors = useCallback((roleId: number) => {
        setSubtreeRootId(null);
        setCollapsedSet(getAncestorCollapsedSet(roleId, roles));
    }, [roles]);

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
        setCollapsedSet(getCollapseAllSet(roles));
    }, [roles]);

    const onResetView = useCallback(() => {
        setSubtreeRootId(null);
        setCollapsedSet(new Set());
        fitView({ padding: 0.3, duration: 400 });
    }, [fitView]);

    useEffect(() => {
        if (subtreeRootId != null && !roles.some((role) => role.id === subtreeRootId)) {
            setSubtreeRootId(null);
        }
    }, [roles, subtreeRootId]);

    const { nodes: rawNodes, edges: rawEdges } = useMemo(
        () => buildNodesAndEdges(roles, collapsedSet, subtreeRootId),
        [roles, collapsedSet, subtreeRootId],
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
        () => layoutNodes(nodesWithToggle, rawEdges, layoutDirection),
        [nodesWithToggle, rawEdges, layoutDirection],
    );

    const [nodes, setNodes, onNodesChange] = useNodesState(layoutedNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(rawEdges);

    useEffect(() => {
        setNodes(layoutedNodes);
        setEdges(rawEdges);
    }, [layoutedNodes, rawEdges, setNodes, setEdges]);

    useEffect(() => {
        if (nodes.length > 0) {
            const timer = setTimeout(() => fitView({ padding: 0.3, duration: 300 }), 100);
            return () => clearTimeout(timer);
        }
    }, [nodes.length, fitView]);

    const onNodeClick: NodeMouseHandler = useCallback(
        (_event, node) => {
            const role = roles.find((item) => item.id === Number(node.id));
            if (role) {
                setSelectedRole(role);
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

    if (roles.length === 0) {
        return (
            <div className="flex size-full items-center justify-center">
                <EmptyState icon={IconMasksTheater} message="نقشی برای نمایش وجود ندارد" />
            </div>
        );
    }

    return (
        <div dir="rtl" className="relative size-full overflow-hidden rounded-xl border bg-background">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onNodeClick={onNodeClick}
                nodeTypes={nodeTypes}
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
                    color="hsl(var(--border))"
                />
                <Controls className="[&>button]:!rounded-md [&>button]:!border [&>button]:!bg-card [&>button]:!text-muted-foreground [&>button]:hover:!bg-accent [&>button]:hover:!text-accent-foreground [&>button]:!transition-colors !rounded-lg !border !border-border !bg-card !shadow-sm !overflow-hidden">
                    <ControlButton
                        onClick={() => handleLayoutChange("TB")}
                        className="!rounded-md !border !bg-card !text-muted-foreground hover:!bg-accent hover:!text-accent-foreground !transition-colors"
                        title="چیدمان عمودی"
                    >
                        <IconArrowsVertical className="size-4" />
                    </ControlButton>
                    <ControlButton
                        onClick={() => handleLayoutChange("LR")}
                        className="!rounded-md !border !bg-card !text-muted-foreground hover:!bg-accent hover:!text-accent-foreground !transition-colors"
                        title="چیدمان افقی"
                    >
                        <IconArrowsHorizontal className="size-4" />
                    </ControlButton>
                    <ControlButton
                        onClick={onExpandAll}
                        className="!rounded-md !border !bg-card !text-muted-foreground hover:!bg-accent hover:!text-accent-foreground !transition-colors"
                        title="بازکردن همه"
                    >
                        <IconArrowsMaximize className="size-4" />
                    </ControlButton>
                    <ControlButton
                        onClick={onCollapseAll}
                        className="!rounded-md !border !bg-card !text-muted-foreground hover:!bg-accent hover:!text-accent-foreground !transition-colors"
                        title="جمع‌کردن همه"
                    >
                        <IconFold className="size-4" />
                    </ControlButton>
                    <ControlButton
                        onClick={onResetView}
                        className="!rounded-md !border !bg-card !text-muted-foreground hover:!bg-accent hover:!text-accent-foreground !transition-colors"
                        title="بازآرایی نمای کامل"
                    >
                        <IconMaximize className="size-4" />
                    </ControlButton>
                </Controls>
                <MiniMap
                    nodeStrokeColor="hsl(var(--border))"
                    nodeColor="hsl(var(--muted))"
                    nodeBorderRadius={4}
                    maskColor="hsl(var(--background))"
                    className="!rounded-lg !border !border-border !shadow-sm"
                />

                {subtreeRootId != null && (
                    <div className="absolute top-4 left-4 z-10">
                        <button
                            onClick={onResetView}
                            className="inline-flex items-center gap-1.5 rounded-lg border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <IconArrowBackUp className="size-3.5" />
                            <span>بازگشت به نمای کامل</span>
                        </button>
                    </div>
                )}

                <div className="absolute bottom-4 left-2 z-10 rounded-lg border bg-card p-2.5 text-xs shadow-sm">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <div className="h-0.5 w-6 rounded bg-foreground opacity-45" />
                            <span className="text-muted-foreground">سلسله‌مراتب (والد)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div
                                className="w-6 border-t border-dashed"
                                style={{ borderColor: "hsl(var(--primary))" }}
                            />
                            <span className="text-muted-foreground">مدیر ماتریسی</span>
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
}: {
    roles: RoleChartRole[];
    isLoading: boolean;
    isError: boolean;
    onRetry: () => void;
}) {
    return (
        <ReactFlowProvider>
            <RoleOrgChartInner roles={roles} isLoading={isLoading} isError={isError} onRetry={onRetry} />
        </ReactFlowProvider>
    );
}
