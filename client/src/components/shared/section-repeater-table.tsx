function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === "object"
        ? (value as Record<string, unknown>)
        : {};
}

function stringValue(value: unknown): string | null {
    if (value === null || value === undefined || value === "") return null;
    return String(value);
}

type SectionRepeaterTableProps = {
    items: unknown;
    columns: {
        label: string;
        render: (item: Record<string, unknown>) => unknown;
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
                                <td key={column.label} className="px-3 py-2">
                                    {stringValue(column.render(item)) ?? "—"}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
