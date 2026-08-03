import type { CSSProperties } from "react";
import dagre from "dagre";
import type { RoleChartRole } from "@/features/rbac/types";

export type ChartDirection = "TB" | "LR";

export type RoleOrgNode = {
    id: string;
    type: "customNode";
    position: { x: number; y: number };
    data: {
        role: RoleChartRole;
        childCount: number;
        collapsed: boolean;
        hasChildren: boolean;
    };
};

export type RoleOrgEdge = {
    id: string;
    source: string;
    target: string;
    type?: string;
    style?: CSSProperties;
};

export function getParent(roles: RoleChartRole[], roleId: number): RoleChartRole | null {
    const role = roles.find((r) => r.id === roleId);
    if (!role || role.parent_id == null) {
        return null;
    }
    return roles.find((r) => r.id === role.parent_id) ?? null;
}

export function getDescendantIds(roleId: number, roles: RoleChartRole[]): Set<number> {
    const descendants = new Set<number>();
    const queue = [roleId];
    while (queue.length > 0) {
        const current = queue.shift()!;
        for (const role of roles) {
            if (role.parent_id === current && !descendants.has(role.id)) {
                descendants.add(role.id);
                queue.push(role.id);
            }
        }
    }
    return descendants;
}

export function getPathToRoot(roleId: number, roles: RoleChartRole[]): number[] {
    const path = [roleId];
    let current = roleId;
    while (true) {
        const parent = getParent(roles, current);
        if (!parent) {
            break;
        }
        path.unshift(parent.id);
        current = parent.id;
    }
    return path;
}

function getNodeIdsWithChildren(roles: RoleChartRole[]): Set<number> {
    const ids = new Set<number>();
    for (const role of roles) {
        if (role.parent_id != null) {
            ids.add(role.parent_id);
        }
    }
    return ids;
}

function collapseExcept(roles: RoleChartRole[], keepExpanded: Set<number>): Set<number> {
    const collapsed = new Set<number>();
    for (const id of getNodeIdsWithChildren(roles)) {
        if (!keepExpanded.has(id)) {
            collapsed.add(id);
        }
    }
    return collapsed;
}

export function getFocusCollapsedSet(focusId: number, roles: RoleChartRole[]): Set<number> {
    const path = getPathToRoot(focusId, roles);
    const descendants = getDescendantIds(focusId, roles);
    return collapseExcept(roles, new Set([...path, focusId, ...descendants]));
}

export function getAncestorCollapsedSet(nodeId: number, roles: RoleChartRole[]): Set<number> {
    return collapseExcept(roles, new Set(getPathToRoot(nodeId, roles)));
}

export function getCollapseAllSet(roles: RoleChartRole[]): Set<number> {
    return getNodeIdsWithChildren(roles);
}

function isDescendantOf(roleId: number, rootId: number, roles: RoleChartRole[]): boolean {
    let current: number | null = roleId;
    while (current != null && current !== rootId) {
        current = roles.find((r) => r.id === current)?.parent_id ?? null;
    }
    return current === rootId;
}

export function buildNodesAndEdges(
    roles: RoleChartRole[],
    collapsedSet: Set<number>,
    subtreeRootId: number | null = null,
): { nodes: RoleOrgNode[]; edges: RoleOrgEdge[] } {
    const isVisible = (roleId: number): boolean => {
        const role = roles.find((r) => r.id === roleId);
        if (!role) {
            return false;
        }
        if (subtreeRootId != null) {
            if (roleId !== subtreeRootId && !isDescendantOf(roleId, subtreeRootId, roles)) {
                return false;
            }
        }
        let current: number | null = role.parent_id;
        while (current != null && current !== subtreeRootId) {
            if (collapsedSet.has(current)) {
                return false;
            }
            current = roles.find((r) => r.id === current)?.parent_id ?? null;
        }
        return true;
    };

    const visibleIds = new Set(roles.filter((r) => isVisible(r.id)).map((r) => r.id));

    const edges: RoleOrgEdge[] = [];
    for (const role of roles) {
        if (!visibleIds.has(role.id)) {
            continue;
        }
        if (role.parent_id != null && visibleIds.has(role.parent_id)) {
            edges.push({
                id: `h-${role.parent_id}-${role.id}`,
                source: String(role.parent_id),
                target: String(role.id),
                type: "smoothstep",
                style: {
                    stroke: "var(--foreground)",
                    strokeWidth: 2,
                    strokeOpacity: 0.45,
                },
            });
        }
        for (const manager of role.matrix_manager_roles ?? []) {
            if (visibleIds.has(manager.id)) {
                edges.push({
                    id: `m-${manager.id}-${role.id}-${manager.manager_type}`,
                    source: String(manager.id),
                    target: String(role.id),
                    type: "smoothstep",
                    style: {
                        stroke: "var(--primary)",
                        strokeWidth: 1.5,
                        strokeOpacity: 0.6,
                        strokeDasharray: "6 4",
                    },
                });
            }
        }
    }

    const nodes: RoleOrgNode[] = roles
        .filter((r) => visibleIds.has(r.id))
        .map((role) => {
            const allChildren = roles.filter((r) => r.parent_id === role.id);
            const visibleChildCount = allChildren.filter((r) => visibleIds.has(r.id)).length;
            return {
                id: String(role.id),
                type: "customNode",
                position: { x: 0, y: 0 },
                data: {
                    role,
                    childCount: collapsedSet.has(role.id) ? allChildren.length : visibleChildCount,
                    collapsed: collapsedSet.has(role.id),
                    hasChildren: allChildren.length > 0,
                },
            };
        });

    return { nodes, edges };
}

export function layoutNodes(
    nodes: RoleOrgNode[],
    edges: RoleOrgEdge[],
    direction: ChartDirection = "TB",
): RoleOrgNode[] {
    const NODE_WIDTH = 210;
    const NODE_HEIGHT = 150;

    const graph = new dagre.graphlib.Graph();
    graph.setDefaultEdgeLabel(() => ({}));
    graph.setGraph({
        rankdir: direction,
        nodesep: direction === "TB" ? 90 : 60,
        ranksep: direction === "TB" ? 110 : 90,
        edgesep: 40,
        marginx: 60,
        marginy: 60,
        align: "UL",
        ranker: "network-simplex",
    });

    for (const node of nodes) {
        graph.setNode(node.id, { width: NODE_WIDTH, height: NODE_HEIGHT });
    }
    for (const edge of edges) {
        graph.setEdge(edge.source, edge.target);
    }

    dagre.layout(graph);

    return nodes.map((node) => {
        const layouted = graph.node(node.id);
        return {
            ...node,
            position: {
                x: layouted.x - NODE_WIDTH / 2,
                y: layouted.y - NODE_HEIGHT / 2,
            },
        };
    });
}
