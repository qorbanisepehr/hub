import * as React from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { IconCheck, IconFilter } from "@tabler/icons-react";
import {
    type Column,
    type RowData,
    type StockFeatures,
} from "@tanstack/react-table";

type DataTableFacetedFilterProps<TData extends RowData, TValue> = {
    column?: Column<StockFeatures, TData, TValue>;
    title?: string;
    options: {
        label: string;
        value: string;
        icon?: React.ComponentType<{ className?: string }>;
    }[];
};

export function DataTableFacetedFilter<TData extends RowData, TValue>({
    column,
    title,
    options = [],
}: DataTableFacetedFilterProps<TData, TValue>) {
    const facets = column?.getFacetedUniqueValues();
    const filterValue = column?.getFilterValue();
    const selectedValues = new Set(
        Array.isArray(filterValue) ? filterValue : [],
    );

    const selectedOption = options.find((opt) =>
        selectedValues.has(opt.value),
    );

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 border-dashed"
                    >
                        <IconFilter className="size-4" />
                        {title}
                        {selectedOption && (
                            <>
                                <Separator
                                    orientation="vertical"
                                    className="mx-2 h-4"
                                />
                                <Badge
                                    variant="secondary"
                                    className="rounded-sm px-1 font-normal"
                                >
                                    {selectedOption.label}
                                </Badge>
                            </>
                        )}
                    </Button>
                }
            />
            <PopoverContent className="w-50 p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <CommandList>
                        <CommandEmpty>نتیجه‌ای یافت نشد.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedValues.has(
                                    option.value,
                                );
                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() => {
                                            if (
                                                option.value === "" ||
                                                option.value === "all"
                                            ) {
                                                column?.setFilterValue(
                                                    undefined,
                                                );
                                                return;
                                            }
                                            if (isSelected) {
                                                column?.setFilterValue(
                                                    undefined,
                                                );
                                            } else {
                                                column?.setFilterValue([
                                                    option.value,
                                                ]);
                                            }
                                        }}
                                    >
                                        <div
                                            className={cn(
                                                "flex size-4 items-center justify-center rounded-sm border border-primary",
                                                isSelected
                                                    ? "bg-primary text-primary-foreground"
                                                    : "opacity-50 [&_svg]:invisible",
                                            )}
                                        >
                                            <IconCheck
                                                className={cn(
                                                    "size-4 text-primary-foreground",
                                                )}
                                            />
                                        </div>
                                        {option.icon && (
                                            <option.icon className="size-4 text-muted-foreground" />
                                        )}
                                        <span>{option.label}</span>
                                        {facets?.get(option.value) && (
                                            <span className="ms-auto flex size-4 items-center justify-center font-mono text-xs">
                                                {facets.get(option.value)}
                                            </span>
                                        )}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        {selectedValues.size > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={() =>
                                            column?.setFilterValue(undefined)
                                        }
                                        className="justify-center text-center"
                                    >
                                        پاک کردن فیلترها
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
