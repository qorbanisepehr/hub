import type { ComponentType, SVGProps } from "react";

/** Any icon component accepting a className (Tabler icons project-wide). */
export type ToolbarIcon = ComponentType<SVGProps<SVGSVGElement> & { className?: string }>;

/**
 * Descriptor for one toolbar button/action. One source: the same object is
 * rendered as an icon <Button> (+ tooltip) on desktop and as a labelled item
 * inside the mobile «...» menu. Permission-gated items render only when the
 * current user holds the permission. No per-page mobile wiring - ever.
 */
export type DataTableToolbarAction = {
    id: string;
    label: string;
    icon?: ToolbarIcon;
    onClick: () => void;
    disabled?: boolean;
    destructive?: boolean;
    /** Backend capability name(s), e.g. `employee.export`. */
    permission?: string | string[];
};
