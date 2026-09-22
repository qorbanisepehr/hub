import type { LucideIcon } from "lucide-react";

/**
 * Descriptor for one toolbar button/action. One source: the same object is
 * rendered as an inline <Button> on desktop and as a DropdownMenuItem inside
 * the mobile «بیشتر» menu. No per-page mobile wiring — ever.
 */
export type DataTableToolbarAction = {
    id: string;
    label: string;
    icon?: LucideIcon;
    onClick: () => void;
    disabled?: boolean;
    destructive?: boolean;
};
