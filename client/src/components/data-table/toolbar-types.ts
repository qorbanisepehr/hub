import type { ComponentType, SVGProps } from "react";

/** Any icon component accepting a className (Tabler icons project-wide). */
export type ToolbarIcon = ComponentType<SVGProps<SVGSVGElement> & { className?: string }>;

/**
 * Descriptor for one toolbar button/action. One source: the same object is
 * rendered as an inline <Button> on desktop and as a full-width button inside
 * the mobile filter sheet. No per-page mobile wiring - ever.
 */
export type DataTableToolbarAction = {
    id: string;
    label: string;
    icon?: ToolbarIcon;
    onClick: () => void;
    disabled?: boolean;
    destructive?: boolean;
};
