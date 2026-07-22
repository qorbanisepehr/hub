import type { AnyFieldApi } from "@tanstack/react-form";
import { IconPlus, IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type FormRepeaterProps = {
    field: AnyFieldApi;
    label: string;
    renderItem: (index: number) => React.ReactNode;
    maxItems?: number;
};

export function FormRepeater({
    field,
    label,
    renderItem,
    maxItems,
}: FormRepeaterProps) {
    const items: unknown[] = field.state.value ?? [];

    const addItem = () => {
        if (maxItems && items.length >= maxItems) return;
        field.handleChange([...items, {}]);
    };

    const removeItem = (index: number) => {
        field.handleChange(items.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{label}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addItem}
                    disabled={maxItems ? items.length >= maxItems : false}
                >
                    <IconPlus className="size-4 ms-1" />
                    افزودن
                </Button>
            </div>

            {items.map((_, index) => (
                <div
                    key={index}
                    className="relative rounded-lg border p-4 space-y-3"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        className={cn(
                            "absolute top-2 start-2 text-destructive",
                        )}
                        onClick={() => removeItem(index)}
                    >
                        <IconTrash className="size-4" />
                    </Button>
                    {renderItem(index)}
                </div>
            ))}

            {items.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    آیتمی اضافه نشده است.
                </p>
            )}
        </div>
    );
}
