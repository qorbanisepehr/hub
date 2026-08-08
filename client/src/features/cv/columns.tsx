import {
    type ColumnDef,
    type StockFeatures,
} from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconDownload, IconEye } from "@tabler/icons-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DataTableColumnHeader } from "@/components/data-table";
import { CV_STATUS_BADGE_VARIANTS, CV_STATUS_LABELS } from "@/features/cv/constants";
import type { Cv, CvStatus } from "@/features/cv/types";
import { toPersianDate } from "@/lib/date-format";

export const cvBankColumns: ColumnDef<StockFeatures, Cv>[] = [
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
            const status = row.getValue("status") as CvStatus;
            return (
                <Badge variant={CV_STATUS_BADGE_VARIANTS[status] ?? "secondary"}>
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
        cell: ({ row }) => {
            const createdAt = row.getValue("created_at") as string | null;
            return (
                <span className="text-sm text-muted-foreground">
                    {createdAt ? toPersianDate(createdAt) : "—"}
                </span>
            );
        },
        meta: { displayName: "تاریخ ایجاد" },
    },
    {
        id: "actions",
        header: "عملیات",
        cell: ({ row }) => {
            const resume = row.original.resume_document;

            return (
                <div className="flex items-center justify-end gap-1">
                    {resume?.download_url && (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            nativeButton={false}
                            render={
                                <a
                                    href={resume.download_url}
                                    aria-label="دانلود رزومه"
                                    title="دانلود رزومه"
                                />
                            }
                        >
                            <IconDownload className="size-4" />
                        </Button>
                    )}
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
                </div>
            );
        },
        enableSorting: false,
        enableHiding: false,
    },
];
