import { useState, useCallback } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import {
    IconPlus,
    IconTrash,
    IconPencil,
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
import { ConfirmAction } from "@/components/shared/confirm-action";

type TableColumn = {
    key: string;
    label: string;
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
}) {
    const items: Record<string, unknown>[] = (field.state.value ??
        []) as Record<string, unknown>[];
    const [activeIndex, setActiveIndex] = useState<number | null>(null);
    const [originalSnapshot, setOriginalSnapshot] = useState<Record<
        string,
        unknown
    > | null>(null);

    const canAdd = !maxItems || items.length < maxItems;
    const isFormOpen = activeIndex !== null;

    const handleStartAdd = useCallback(() => {
        field.handleChange([...items, {}]);
        setActiveIndex(items.length);
        setOriginalSnapshot(null);
    }, [items, field]);

    const handleStartEdit = useCallback(
        (index: number) => {
            setOriginalSnapshot({ ...items[index] });
            setActiveIndex(index);
        },
        [items],
    );

    const handleConfirm = useCallback(() => {
        setActiveIndex(null);
        setOriginalSnapshot(null);
    }, []);

    const handleCancel = useCallback(() => {
        if (activeIndex === null) return;
        if (originalSnapshot !== null) {
            const newItems = [...items];
            newItems[activeIndex] = originalSnapshot;
            field.handleChange(newItems);
        } else {
            field.handleChange(items.filter((_, i) => i !== activeIndex));
        }
        setActiveIndex(null);
        setOriginalSnapshot(null);
    }, [activeIndex, originalSnapshot, items, field]);

    const handleDelete = useCallback(
        (index: number) => {
            field.handleChange(items.filter((_, i) => i !== index));
            if (activeIndex === index) {
                setActiveIndex(null);
                setOriginalSnapshot(null);
            } else if (activeIndex !== null && activeIndex > index) {
                setActiveIndex(activeIndex - 1);
            }
        },
        [items, field, activeIndex],
    );

    const isAddMode = activeIndex !== null && originalSnapshot === null;
    const isEditMode = activeIndex !== null && originalSnapshot !== null;

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
                                <TableHead className="w-10">#</TableHead>
                                {columns.map((col) => (
                                    <TableHead key={col.key}>
                                        {col.label}
                                    </TableHead>
                                ))}
                                <TableHead className="w-24 text-center">
                                    عملیات
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item, index) => {
                                const summary = getSummary(item, index);
                                return (
                                    <TableRow
                                        key={index}
                                        className={cn(
                                            activeIndex === index &&
                                                "bg-muted/50",
                                        )}
                                    >
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
                                                    : String(
                                                          summary[col.key] ??
                                                              "—",
                                                      )}
                                            </TableCell>
                                        ))}
                                        <TableCell>
                                            <div className="flex items-center justify-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    onClick={() =>
                                                        handleStartEdit(index)
                                                    }
                                                    disabled={isFormOpen}
                                                >
                                                    <IconPencil className="size-4" />
                                                </Button>
                                                <ConfirmAction
                                                    trigger={
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            className="text-destructive"
                                                            disabled={isFormOpen}
                                                        >
                                                            <IconTrash className="size-4" />
                                                        </Button>
                                                    }
                                                    stopPropagation
                                                    onConfirm={() => handleDelete(index)}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
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

            {isAddMode && activeIndex !== null && (
                <div className="rounded-lg border border-primary/30 p-4 space-y-4">
                    <span className="text-sm font-medium text-primary">
                        آیتم جدید
                    </span>
                    {renderItem(activeIndex)}
                    <div className="flex items-center gap-2 pt-2 border-t">
                        <Button type="button" size="sm" onClick={handleConfirm}>
                            <IconCheck className="size-4 ms-1" />
                            تایید
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
            )}

            {isEditMode && activeIndex !== null && (
                <div className="rounded-lg border border-orange-300 p-4 space-y-4 dark:border-orange-700">
                    <span className="text-sm font-medium text-orange-600 dark:text-orange-400">
                        ویرایش آیتم {activeIndex + 1}
                    </span>
                    {renderItem(activeIndex)}
                    <div className="flex items-center gap-2 pt-2 border-t">
                        <Button type="button" size="sm" onClick={handleConfirm}>
                            <IconCheck className="size-4 ms-1" />
                            ذخیره
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
            field.handleChange(items.filter((_, i) => i !== index));
            if (expandedIndex === index) {
                setExpandedIndex(null);
            } else if (expandedIndex !== null && expandedIndex > index) {
                setExpandedIndex(expandedIndex - 1);
            }
        },
        [items, field, expandedIndex],
    );

    const toggleExpand = useCallback(
        (index: number) => {
            setExpandedIndex(expandedIndex === index ? null : index);
        },
        [expandedIndex],
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
                            <ConfirmAction
                                trigger={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        className="text-destructive shrink-0"
                                    >
                                        <IconTrash className="size-4" />
                                    </Button>
                                }
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
