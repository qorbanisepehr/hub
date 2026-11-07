import { useMemo } from "react";
import { IconLayoutGrid, IconTable } from "@tabler/icons-react";
import { z } from "zod";

import type { NavigateFn } from "@/hooks/use-table-url-state";
import type { DataTableViewOption } from "./view-mode-switcher";

/**
 * The search param every list route declares to offer alternate renderings
 * of the same rows (D15). URL-only on purpose: a card view is shareable, and
 * nothing is remembered that the address bar does not say.
 */
export const listViewSchema = z.enum(["table", "card"]).optional();

/** The baseline every card-capable list offers: the table and a card grid. */
export const TABLE_AND_CARD_VIEWS: DataTableViewOption[] = [
    { mode: "table", label: "جدول", icon: IconTable },
    { mode: "card", label: "کارتی", icon: IconLayoutGrid },
];

type UseListViewParams = {
    search: Record<string, unknown>;
    navigate: NavigateFn;
    /** Two lists on one route need two keys; defaults to `view`. */
    key?: string;
};

/**
 * Reads and writes the active list rendering. `table` is the absent value, so
 * the default URL stays the short one and a shared link only carries a
 * non-default mode.
 */
export function useListView({
    search,
    navigate,
    key = "view",
}: UseListViewParams) {
    const view = search[key] === "card" ? "card" : "table";

    return useMemo(
        () => ({
            view,
            setView: (mode: string) => {
                navigate({
                    search: (prev) => ({
                        ...prev,
                        [key]: mode === "table" ? undefined : mode,
                    }),
                });
            },
        }),
        [view, navigate, key],
    );
}
