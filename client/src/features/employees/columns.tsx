import { type ColumnDef } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye, IconPencil } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import {
    genderLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";
import type { Employee } from "@/features/employees/types";

export const employeeColumns: ColumnDef<Employee>[] = [
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
    },
    {
        accessorKey: "gender",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="جنسیت" />
        ),
        cell: ({ row }) => (
            <Badge variant="outline">
                {genderLabels[row.getValue("gender") as string] ??
                    row.getValue("gender")}
            </Badge>
        ),
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
                <PermissionGuard
                    permission={[
                        "employee.update_own",
                        "employee.update_all",
                    ]}
                >
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
                </PermissionGuard>
            </div>
        ),
        enableSorting: false,
        enableHiding: false,
    },
];
