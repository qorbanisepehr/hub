import { useState, useCallback, Fragment } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import {
    IconPlus,
    IconChevronDown,
    IconChevronUp,
    IconCheck,
    IconX,
    IconLayoutList,
    IconLayoutGrid,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { toPersianDate } from "@/lib/date-format";

export type TableColumn = {
    key: string;
    label: string;
    /** Render the cell value as a Persian (Jalali) date */
    type?: "date";
    render?: (value: unknown, item: unknown, index: number) => React.ReactNode;
};

type FormRepeaterProps = {
    field: AnyFieldApi;
    label: string;
    columns?: TableColumn[];
    renderItem: (index: number) => React.ReactNode;
    getSummary?: (
        item: Record<string, unknown>,
        index: number,
    ) => Record<string, unknown>;
    renderHeader?: (
        item: Record<string, unknown>,
        index: number,
    ) => React.ReactNode;
    maxItems?: number;
    emptyMessage?: string;
    defaultMode?: "table" | "card";
    /** Called after add/edit/delete to persist the updated list to the server */
    onPersist?: (items: Record<string, unknown>[]) => void;
};

export function FormRepeater({
    field,
    label,
    columns,
    renderItem,
    getSummary,
    renderHeader,
    maxItems,
    emptyMessage = "آیتمی اضافه نشده است.",
    defaultMode = "table",
    onPersist,
}: FormRepeaterProps) {
    const [mode, setMode] = useState<"table" | "card">(defaultMode);

    const hasColumns = columns && columns.length > 0;
    const hasSummary = typeof getSummary === "function";
    const canToggle = hasColumns && hasSummary;

    const items: Record<string, unknown>[] = (field.state.value ??
        []) as Record<string, unknown>[];
    const effectiveMode = canToggle ? mode : "card";

    const handleToggle = useCallback(() => {
        setMode((prev) => (prev === "table" ? "card" : "table"));
    }, []);

    const toggleButton = canToggle ? (
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            onClick={handleToggle}
            title={effectiveMode === "table" ? "نمایش کارتی" : "نمایش جدولی"}
        >
            {effectiveMode === "table" ? (
                <IconLayoutGrid className="size-4" />
            ) : (
                <IconLayoutList className="size-4" />
            )}
        </Button>
    ) : null;

    if (effectiveMode === "table" && hasColumns && hasSummary) {
        return (
            <TableRepeaterInner
                field={field}
                label={label}
                columns={columns!}
                renderItem={renderItem}
                getSummary={getSummary!}
                maxItems={maxItems}
                emptyMessage={emptyMessage}
                toggleButton={toggleButton}
                onPersist={onPersist}
            />
        );
    }

    return (
        <CardRepeaterInner
            field={field}
            label={label}
            renderItem={renderItem}
            renderHeader={renderHeader ?? ((_, i) => <span>آیتم {i + 1}</span>)}
            maxItems={maxItems}
            emptyMessage={emptyMessage}
            toggleButton={toggleButton}
            onPersist={onPersist}
        />
    );
}

// ── Table Repeater ──

function TableRepeaterInner({
    field,
    label,
    columns,
    renderItem,
    getSummary,
    maxItems,
    emptyMessage,
    toggleButton,
    onPersist,
}: {
    field: AnyFieldApi;
    label: string;
    columns: TableColumn[];
    renderItem: (index: number) => React.ReactNode;
    getSummary: (
        item: Record<string, unknown>,
        index: number,
    ) => Record<string, unknown>;
    maxItems?: number;
    emptyMessage: string;
    toggleButton: React.ReactNode;
    onPersist?: (items: Record<string, unknown>[]) => void;
}) {
    const items: Record<string, unknown>[] = (field.state.value ??
        []) as Record<string, unknown>[];
    const [expandedIndex, setExpandedIndex] = useState<number | null>(null);
    const [originalSnapshot, setOriginalSnapshot] = useState<Record<
        string,
        unknown
    > | null>(null);

    const canAdd = !maxItems || items.length < maxItems;
    const isFormOpen = expandedIndex !== null;

    const handleStartAdd = useCallback(() => {
        field.handleChange([...items, {}]);
        setExpandedIndex(items.length);
        setOriginalSnapshot(null);
    }, [items, field]);

    const handleToggleExpand = useCallback(
        (index: number) => {
            if (expandedIndex === index) {
                handleCancel();
                return;
            }
            if (isFormOpen) return;
            setOriginalSnapshot({ ...items[index] });
            setExpandedIndex(index);
        },
        [expandedIndex, isFormOpen, items],
    );

    const handleConfirm = useCallback(() => {
        setExpandedIndex(null);
        setOriginalSnapshot(null);
        onPersist?.(field.state.value as Record<string, unknown>[]);
    }, [field, onPersist]);

    const handleCancel = useCallback(() => {
        if (expandedIndex === null) return;
        if (originalSnapshot !== null) {
            const newItems = [...items];
            newItems[expandedIndex] = originalSnapshot;
            field.handleChange(newItems);
        } else {
            field.handleChange(items.filter((_, i) => i !== expandedIndex));
        }
        setExpandedIndex(null);
        setOriginalSnapshot(null);
    }, [expandedIndex, originalSnapshot, items, field]);

    const handleDelete = useCallback(
        (index: number) => {
            const newItems = items.filter((_, i) => i !== index);
            field.handleChange(newItems);
            if (expandedIndex === index) {
                setExpandedIndex(null);
                setOriginalSnapshot(null);
            } else if (expandedIndex !== null && expandedIndex > index) {
                setExpandedIndex(expandedIndex - 1);
            }
            onPersist?.(newItems as Record<string, unknown>[]);
        },
        [items, field, expandedIndex, onPersist],
    );

    const isAddMode = expandedIndex !== null && originalSnapshot === null;
    const isEditMode = expandedIndex !== null && originalSnapshot !== null;

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{label}</span>
                <div className="flex items-center gap-1">
                    {toggleButton}
                    {canAdd && !isFormOpen && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleStartAdd}
                        >
                            <IconPlus className="size-4 ms-1" />
                            افزودن
                        </Button>
                    )}
                </div>
            </div>

            {items.length > 0 && (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10" />
                                <TableHead className="w-10">#</TableHead>
                                {columns.map((col) => (
                                    <TableHead key={col.key}>
                                        {col.label}
                                    </TableHead>
                                ))}
                                <TableHead className="w-10 text-center" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item, index) => {
                                const summary = getSummary(item, index);
                                const isExpanded = expandedIndex === index;
                                return (
                                    <Fragment key={`item-${index}`}>
                                        <TableRow
                                            className={cn(
                                                isExpanded &&
                                                    "bg-muted/50",
                                            )}
                                        >
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() =>
                                                        handleToggleExpand(index)
                                                    }
                                                    disabled={
                                                        isFormOpen &&
                                                        !isExpanded
                                                    }
                                                >
                                                    {isExpanded ? (
                                                        <IconChevronUp className="size-4" />
                                                    ) : (
                                                        <IconChevronDown className="size-4" />
                                                    )}
                                                </Button>
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {index + 1}
                                            </TableCell>
                                            {columns.map((col) => (
                                                <TableCell key={col.key}>
                                                    {col.render
                                                        ? col.render(
                                                              summary[col.key],
                                                              item,
                                                              index,
                                                          )
                                                        : col.type === "date"
                                                          ? toPersianDate(
                                                                summary[col.key] as
                                                                    | string
                                                                    | null
                                                                    | undefined,
                                                            )
                                                          : String(
                                                                summary[col.key] ??
                                                                    "—",
                                                            )}
                                                </TableCell>
                                            ))}
                                            <TableCell>
                                                <div className="flex items-center justify-center">
                                                    <ConfirmDeleteButton
                                                        iconOnly
                                                        disabled={isFormOpen}
                                                        stopPropagation
                                                        onConfirm={() =>
                                                            handleDelete(index)
                                                        }
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                        {isExpanded && (
                                            <TableRow className="bg-muted/30">
                                                <TableCell colSpan={columns.length + 3}>
                                                    <div className="p-4 space-y-4">
                                                        <span className="text-sm font-medium text-muted-foreground">
                                                            {isAddMode
                                                                ? "آیتم جدید"
                                                                : `جزئیات آیتم ${index + 1}`}
                                                        </span>
                                                        {renderItem(index)}
                                                        <div className="flex items-center gap-2 pt-2 border-t">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={handleConfirm}
                                                            >
                                                                <IconCheck className="size-4 ms-1" />
                                                                {isAddMode
                                                                    ? "تایید"
                                                                    : "ذخیره"}
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={handleCancel}
                                                            >
                                                                <IconX className="size-4 ms-1" />
                                                                انصراف
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}

            {items.length === 0 && !isFormOpen && (
                <p className="text-sm text-muted-foreground text-center py-4">
                    {emptyMessage}
                </p>
            )}
        </div>
    );
}

// ── Card Repeater ──

function CardRepeaterInner({
    field,
    label,
    renderItem,
    renderHeader,
    maxItems,
    emptyMessage,
    toggleButton,
    onPersist,
}: {
    field: AnyFieldApi;
    label: string;
    renderItem: (index: number) => React.ReactNode;
    renderHeader: (
        item: Record<string, unknown>,
        index: number,
    ) => React.ReactNode;
    maxItems?: number;
    emptyMessage: string;
    toggleButton: React.ReactNode;
    onPersist?: (items: Record<string, unknown>[]) => void;
}) {
    const items: Record<string, unknown>[] = (field.state.value ??
        []) as Record<string, unknown>[];
    const [expandedIndex, setExpandedIndex] = useState<number | null>(null);

    const canAdd = !maxItems || items.length < maxItems;

    const handleAdd = useCallback(() => {
        field.handleChange([...items, {}]);
        setExpandedIndex(items.length);
    }, [items, field]);

    const handleDelete = useCallback(
        (index: number) => {
            const newItems = items.filter((_, i) => i !== index);
            field.handleChange(newItems);
            if (expandedIndex === index) {
                setExpandedIndex(null);
            } else if (expandedIndex !== null && expandedIndex > index) {
                setExpandedIndex(expandedIndex - 1);
            }
            onPersist?.(newItems as Record<string, unknown>[]);
        },
        [items, field, expandedIndex, onPersist],
    );

    const toggleExpand = useCallback(
        (index: number) => {
            const isCollapsing = expandedIndex === index;
            setExpandedIndex(isCollapsing ? null : index);
            if (isCollapsing) {
                onPersist?.(field.state.value as Record<string, unknown>[]);
            }
        },
        [expandedIndex, field, onPersist],
    );

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{label}</span>
                <div className="flex items-center gap-1">
                    {toggleButton}
                    {canAdd && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleAdd}
                        >
                            <IconPlus className="size-4 ms-1" />
                            افزودن
                        </Button>
                    )}
                </div>
            </div>

            {items.length === 0 && (
                <p className="text-sm text-muted-foreground text-center py-4">
                    {emptyMessage}
                </p>
            )}

            {items.map((item, index) => {
                const isExpanded = expandedIndex === index;
                return (
                    <Card
                        key={index}
                        className={cn(isExpanded && "border-primary/30")}
                    >
                        <CardHeader
                            className="flex flex-row items-center justify-between cursor-pointer py-3"
                            onClick={() => toggleExpand(index)}
                        >
                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                {isExpanded ? (
                                    <IconChevronUp className="size-4 text-muted-foreground" />
                                ) : (
                                    <IconChevronDown className="size-4 text-muted-foreground" />
                                )}
                                {renderHeader(item, index)}
                            </CardTitle>
                            <ConfirmDeleteButton
                                iconOnly
                                stopPropagation
                                onConfirm={() => handleDelete(index)}
                            />
                        </CardHeader>
                        {isExpanded && (
                            <CardContent className="pt-0">
                                {renderItem(index)}
                            </CardContent>
                        )}
                    </Card>
                );
            })}
        </div>
    );
}
