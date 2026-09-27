import {
    type ColumnDef,
    type StockFeatures,
} from "@tanstack/react-table";
import { Link } from "@tanstack/react-router";
import { IconEye } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/data-table";
import type { Questionnaire } from "@/features/questionnaire/types";
import {
    QUESTIONNAIRE_STATUS_BADGE_VARIANTS,
    QUESTIONNAIRE_STATUS_LABELS,
    type QuestionnaireStatus,
} from "@/features/questionnaire/constants";
import { toPersianDate } from "@/lib/date-format";

/**
 * Management list columns for questionnaires of every status. The endpoint
 * serves every status, so the status badge column (server-sortable) carries
 * the review workflow state at a glance.
 */
export const questionnaireBankColumns: ColumnDef<StockFeatures, Questionnaire>[] = [
    {
        id: "full_name",
        accessorFn: (row) => `${row.first_name} ${row.last_name}`,
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="نام و نام خانوادگی" />
        ),
        cell: ({ row }) => (
            <Link
                to="/questionnaires/$id"
                params={{ id: String(row.original.id) }}
                className="text-sm font-medium hover:text-primary transition-colors"
            >
                {row.original.first_name} {row.original.last_name}
            </Link>
        ),
        meta: { displayName: "نام و نام خانوادگی" },
        // The management index sorts only by real columns (first/last name
        // individually, not the combined id), so don't advertise a sort here.
        enableSorting: false,
    },
    {
        accessorKey: "status",
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="وضعیت" />
        ),
        cell: ({ row }) => {
            const status = row.getValue("status") as QuestionnaireStatus;
            return (
                <Badge variant={QUESTIONNAIRE_STATUS_BADGE_VARIANTS[status]}>
                    {QUESTIONNAIRE_STATUS_LABELS[status] ?? status}
                </Badge>
            );
        },
        meta: { displayName: "وضعیت" },
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
        // backend SORTABLE omits mobile — no server-side sort
        enableSorting: false,
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
        // backend SORTABLE omits email — no server-side sort
        enableSorting: false,
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
        // backend SORTABLE omits version — no server-side sort
        enableSorting: false,
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
        cell: ({ row }) => (
            <div className="flex items-center justify-end gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    nativeButton={false}
                    render={
                        <Link
                            to="/questionnaires/$id"
                            params={{ id: String(row.original.id) }}
                        />
                    }
                >
                    <IconEye className="size-4" />
                </Button>
            </div>
        ),
        enableSorting: false,
        enableHiding: false,
    },
];