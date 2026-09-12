import { Fragment, useState, type ReactNode } from "react";
import { IconChevronDown, IconChevronRight } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { RepeaterEmptyState } from "./repeater-empty-state";

function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === "object"
        ? (value as Record<string, unknown>)
        : {};
}

type SectionRepeaterTableColumn = {
    label: string;
    /**
     * Cell content: usually a primitive; may return a ReactNode
     * (e.g. a status badge). Non-empty values are rendered as-is.
     */
    render: (
        item: Record<string, unknown>,
        index: number,
    ) => unknown;
};

type SectionRepeaterTableProps = {
    items: unknown;
    columns: SectionRepeaterTableColumn[];
    emptyLabel: string;
    /**
     * When provided, every row gains a chevron toggle and this renders the
     * row's expanded content (spanning all columns), mirroring the
     * data-table expandable-row pattern.
     */
    renderExpandedRow?: (
        item: Record<string, unknown>,
        index: number,
    ) => ReactNode;
};

export function SectionRepeaterTable({
    items,
    columns,
    emptyLabel,
    renderExpandedRow,
}: SectionRepeaterTableProps) {
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});

    const list = Array.isArray(items) ? items.map(asRecord) : [];
    if (list.length === 0) {
        return <RepeaterEmptyState message={emptyLabel} />;
    }
    const cellValue = (value: unknown): ReactNode =>
        value === null || value === undefined || value === "" ? "-" : (value as ReactNode);
    const colCount = columns.length + (renderExpandedRow ? 1 : 0);

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/50">
                        {renderExpandedRow && (
                            <th className="w-10 px-3 py-2 text-right font-medium" />
                        )}
                        {columns.map((column) => (
                            <th
                                key={column.label}
                                className="px-3 py-2 text-right font-medium"
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {list.map((item, index) => (
                        <Fragment key={index}>
                            <tr className="border-b last:border-b-0">
                                {renderExpandedRow && (
                                    <td className="px-3 py-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-expanded={expanded[index] ?? false}
                                            onClick={() =>
                                                setExpanded((prev) => ({
                                                    ...prev,
                                                    [index]: !prev[index],
                                                }))
                                            }
                                        >
                                            {expanded[index] ? (
                                                <IconChevronDown className="size-4" />
                                            ) : (
                                                <IconChevronRight className="size-4 rtl:-scale-x-100" />
                                            )}
                                        </Button>
                                    </td>
                                )}
                                {columns.map((column) => (
                                    <td
                                        key={column.label}
                                        className="px-3 py-2"
                                    >
                                        {cellValue(
                                            column.render(item, index),
                                        )}
                                    </td>
                                ))}
                            </tr>
                            {renderExpandedRow && expanded[index] && (
                                <tr className="border-b last:border-b-0">
                                    <td
                                        colSpan={colCount}
                                        className="bg-muted/30 p-0"
                                    >
                                        {renderExpandedRow(item, index)}
                                    </td>
                                </tr>
                            )}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
