import type { ReactNode } from "react";

function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === "object"
        ? (value as Record<string, unknown>)
        : {};
}

type SectionRepeaterTableProps = {
    items: unknown;
    columns: {
        label: string;
        /**
         * Cell content: usually a primitive; may return a ReactNode
         * (e.g. a status badge). Non-empty values are rendered as-is.
         */
        render: (
            item: Record<string, unknown>,
            index: number,
        ) => unknown;
    }[];
    emptyLabel: string;
};

export function SectionRepeaterTable({
    items,
    columns,
    emptyLabel,
}: SectionRepeaterTableProps) {
    const list = Array.isArray(items) ? items.map(asRecord) : [];
    if (list.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
    }
    const cellValue = (value: unknown): ReactNode =>
        value === null || value === undefined || value === "" ? "—" : (value as ReactNode);
    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/50">
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
                        <tr key={index} className="border-b last:border-b-0">
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
                    ))}
                </tbody>
            </table>
        </div>
    );
}
