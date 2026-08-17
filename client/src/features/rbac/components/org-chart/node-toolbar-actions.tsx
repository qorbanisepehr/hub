import {
    IconArrowUp,
    IconCrosshair,
    IconGitBranch,
} from "@tabler/icons-react";
import { stopPropagation } from "./utils";

type NodeToolbarActionsProps = {
    roleId: number;
    onShowAncestors?: (id: number) => void;
    onShowSubtree?: (id: number) => void;
    onFocus?: (id: number) => void;
};

export function NodeToolbarActions({
    roleId,
    onShowAncestors,
    onShowSubtree,
    onFocus,
}: NodeToolbarActionsProps) {
    return (
        <>
            <button
                onClick={stopPropagation(onShowAncestors, roleId)}
                className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                title="نمایش مسیر تا نقش ریشه"
            >
                <IconArrowUp className="size-3" />
            </button>
            <button
                onClick={stopPropagation(onShowSubtree, roleId)}
                className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                title="نمایش زیرمجموعه به عنوان ریشه"
            >
                <IconGitBranch className="size-3" />
            </button>
            <button
                onClick={stopPropagation(onFocus, roleId)}
                className="flex size-5 items-center justify-center rounded-md border bg-card text-muted-foreground transition-all cursor-pointer hover:bg-accent hover:text-foreground"
                title="تمرکز روی این نقش"
            >
                <IconCrosshair className="size-3" />
            </button>
        </>
    );
}
