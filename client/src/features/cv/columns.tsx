import { type ColumnDef } from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import { CV_STATUS_LABELS } from "@/features/cv/constants";
import type { Cv } from "@/features/cv/types";

const statusVariants: Record<string, "default" | "secondary" | "outline"> = {
    draft: "secondary",
    submitted: "default",
    reviewed: "outline",
};

export const cvBankColumns: ColumnDef<Cv>[] = [
    {
        id: "full_name",
        accessorFn: (row) => `${row.first_name} ${row.last_name}`,
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نام و نام خانوادگی" />
        ),
        cell: ({ row }) => (
            <Link
                to="/cvs/$id"
                params={{ id: String(row.original.id) }}
                className="text-sm font-medium hover:text-primary transition-colors"
            >
                {row.original.first_name} {row.original.last_name}
            </Link>
        ),
        meta: { displayName: "نام و نام خانوادگی" },
    },
    {
        accessorKey: "mobile",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="موبایل" />
        ),
        cell: ({ row }) => (
            <span dir="ltr" className="text-sm">
                {row.getValue("mobile")}
            </span>
        ),
        meta: { displayName: "موبایل" },
    },
    {
        accessorKey: "email",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="ایمیل" />
        ),
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {row.getValue("email") ?? "—"}
            </span>
        ),
        meta: { displayName: "ایمیل" },
    },
    {
        accessorKey: "status",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="وضعیت" />
        ),
        cell: ({ row }) => {
            const status = row.getValue("status") as string;
            return (
                <Badge variant={statusVariants[status] ?? "secondary"}>
                    {CV_STATUS_LABELS[status] ?? status}
                </Badge>
            );
        },
        meta: { displayName: "وضعیت" },
    },
    {
        accessorKey: "version",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نسخه" />
        ),
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {row.getValue("version")}
            </span>
        ),
        meta: { displayName: "نسخه" },
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="تاریخ ایجاد" />
        ),
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground">
                {row.getValue("created_at") ?? "—"}
            </span>
        ),
        meta: { displayName: "تاریخ ایجاد" },
    },
    {
        id: "actions",
        header: "عملیات",
        cell: ({ row }) => (
            <Button
                variant="ghost"
                size="icon-sm"
                nativeButton={false}
                render={
                    <Link
                        to="/cvs/$id"
                        params={{ id: String(row.original.id) }}
                    />
                }
            >
                <IconEye className="size-4" />
            </Button>
        ),
        enableSorting: false,
        enableHiding: false,
    },
];
