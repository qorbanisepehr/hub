import { type ColumnDef, type StockFeatures } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye, IconPencil } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import {
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";
import type { Employee } from "@/features/employees/types";

export const employeeColumns: ColumnDef<StockFeatures, Employee>[] = [
    {
        accessorKey: "personnel_code",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="کد پرسنلی" />
        ),
        cell: ({ row }) => (
            <span dir="ltr" className="text-sm font-medium">
                {row.getValue("personnel_code")}
            </span>
        ),
        meta: { displayName: "کد پرسنلی" },
        enableHiding: false,
    },
    {
        id: "full_name",
        accessorFn: (row) => `${row.first_name} ${row.last_name}`,
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نام و نام خانوادگی" />
        ),
        cell: ({ row }) => (
            <Link
                to="/employees/$id"
                params={{ id: String(row.original.id) }}
                className="text-sm font-medium hover:text-primary transition-colors"
            >
                {row.original.first_name} {row.original.last_name}
            </Link>
        ),
        meta: { displayName: "نام و نام خانوادگی" },
    },
    {
        accessorKey: "gender",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="جنسیت" />
        ),
        cell: ({ row }) => (
            <Badge variant="outline">
                {(row.getValue("gender") as string | null) ?? "—"}
            </Badge>
        ),
        meta: { displayName: "جنسیت" },
    },
    {
        accessorKey: "employment_status",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="وضعیت" />
        ),
        cell: ({ row }) => {
            const status = row.getValue("employment_status") as string | null;
            return (
                <Badge variant={statusVariants[status ?? ""] ?? "secondary"}>
                    {statusLabels[status ?? ""] ?? status}
                </Badge>
            );
        },
        meta: { displayName: "وضعیت" },
    },
    {
        accessorKey: "hire_date",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ استخدام" />
        ),
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {row.getValue("hire_date") ?? "—"}
            </span>
        ),
        meta: { displayName: "تاریخ استخدام" },
    },
    {
        id: "actions",
        header: "عملیات",
        cell: ({ row }) => (
            <div className="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    nativeButton={false}
                    render={
                        <Link
                            to="/employees/$id"
                            params={{ id: String(row.original.id) }}
                        />
                    }
                >
                    <IconEye className="size-4" />
                </Button>
                {row.original.capabilities.edit && (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        nativeButton={false}
                        render={
                            <Link
                                to="/employees/$id/edit"
                                params={{ id: String(row.original.id) }}
                            />
                        }
                    >
                        <IconPencil className="size-4" />
                    </Button>
                )}
            </div>
        ),
        enableSorting: false,
        enableHiding: false,
    },
];
