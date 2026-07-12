import * as React from "react";
import { Link, useNavigate, useParams } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { saveAs } from "file-saver";
import {
    IconArrowRight,
    IconDownload,
    IconFile,
    IconFileUpload,
    IconLoader2,
    IconPencil,
    IconTrash,
    IconUsers,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchEmployee, deleteEmployee } from "@/features/employees/api";
import { fetchTrashedDocuments, bulkDownloadDocuments } from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";
import { DocumentSection } from "@/features/documents/components/document-section";
import { DocumentUploadModal } from "@/features/documents/components/document-upload-modal";
import { DocumentTrashModal } from "@/features/documents/components/document-trash-modal";

import {
    genderLabels,
    maritalLabels,
    educationLabels,
    employmentLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-baseline gap-2 py-2 border-b last:border-b-0">
            <span className="text-sm text-muted-foreground min-w-32">
                {label}
            </span>
            <span className="text-sm font-medium">{value ?? "—"}</span>
        </div>
    );
}

export function EmployeeViewPage() {
    const { id } = useParams({ from: "/protected/employees/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [uploadOpen, setUploadOpen] = React.useState(false);
    const [trashOpen, setTrashOpen] = React.useState(false);
    const [selectedIds, setSelectedIds] = React.useState<number[]>([]);
    const [isDownloading, setIsDownloading] = React.useState(false);
    const [downloadError, setDownloadError] = React.useState<string | null>(null);

    const { data: employee, isLoading } = useQuery({
        queryKey: ["employee", Number(id)],
        queryFn: async () => {
            const { data } = await fetchEmployee(Number(id));
            return data.data;
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteEmployee(Number(id)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["employees"] });
            navigate({ to: "/employees" });
        },
    });

    const { data: trashedDocuments } = useQuery({
        queryKey: ["employee-documents", Number(id), "trash"],
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(Number(id));
            return data.data;
        },
    });

    const trashCount = trashedDocuments?.length ?? 0;

    async function handleBulkDownload() {
        setIsDownloading(true);
        setDownloadError(null);
        try {
            const response = await bulkDownloadDocuments(
                Number(id),
                selectedIds.length > 0 ? selectedIds : undefined,
            );
            const blob = new Blob([response.data as BlobPart], { type: "application/zip" });
            saveAs(blob, `${employee?.personnel_code ?? id}.zip`);
        } catch (error) {
            setDownloadError(getApiError(error));
        } finally {
            setIsDownloading(false);
        }
    }

    if (isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <Skeleton className="h-8 w-56" />
                        <Skeleton className="h-4 w-32" />
                    </div>
                    <div className="flex items-center gap-2">
                        <Skeleton className="h-8 w-8 rounded-lg" />
                        <Skeleton className="h-8 w-24 rounded-lg" />
                        <Skeleton className="h-8 w-24 rounded-lg" />
                    </div>
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <Skeleton className="h-5 w-28" />
                            <Skeleton className="h-4 w-40" />
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-baseline gap-2 py-2">
                                    <Skeleton className="h-4 w-24 shrink-0" />
                                    <Skeleton className="h-4 w-32" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <Skeleton className="h-5 w-28" />
                            <Skeleton className="h-4 w-40" />
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div key={i} className="flex items-baseline gap-2 py-2">
                                    <Skeleton className="h-4 w-24 shrink-0" />
                                    <Skeleton className="h-4 w-32" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="space-y-2">
                                <Skeleton className="h-5 w-24" />
                                <Skeleton className="h-4 w-48" />
                            </div>
                            <div className="flex items-center gap-2">
                                <Skeleton className="h-7 w-28 rounded-lg" />
                                <Skeleton className="h-7 w-28 rounded-lg" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Skeleton key={i} className="h-14 w-full rounded-xl" />
                        ))}
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (!employee) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-muted-foreground">
                <IconUsers className="size-12 opacity-30" />
                <p>کارمند مورد نظر یافت نشد</p>
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/employees" />}
                >
                    بازگشت به لیست
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {employee.first_name} {employee.last_name}
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        کد پرسنلی: {employee.personnel_code}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={<Link to="/employees" />}
                    >
                        <IconArrowRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={
                            <Link
                                to="/employees/$id/edit"
                                params={{ id: String(employee.id) }}
                            />
                        }
                    >
                        <IconPencil className="size-4" />
                        ویرایش
                    </Button>
                    <ConfirmDeleteButton
                        onConfirm={() => deleteMutation.mutate()}
                        isPending={deleteMutation.isPending}
                    />
                </div>
            </div>

            {deleteMutation.error && (
                <div className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                    {getApiError(deleteMutation.error)}
                </div>
            )}

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات فردی</CardTitle>
                        <CardDescription>اطلاعات هویتی کارمند</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow
                            label="کد پرسنلی"
                            value={
                                <span dir="ltr">{employee.personnel_code}</span>
                            }
                        />
                        <InfoRow
                            label="نام و نام خانوادگی"
                            value={`${employee.first_name} ${employee.last_name}`}
                        />
                        <InfoRow
                            label="جنسیت"
                            value={
                                <Badge variant="outline">
                                    {genderLabels[employee.gender] ??
                                        employee.gender}
                                </Badge>
                            }
                        />
                        <InfoRow
                            label="تاریخ تولد"
                            value={employee.birth_date ?? "—"}
                        />
                        <InfoRow
                            label="کد ملی"
                            value={employee.id_number ?? "—"}
                        />
                        <InfoRow
                            label="وضعیت تاهل"
                            value={
                                employee.marital_status
                                    ? (maritalLabels[employee.marital_status] ??
                                      employee.marital_status)
                                    : "—"
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات شغلی</CardTitle>
                        <CardDescription>وضعیت استخدامی کارمند</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow
                            label="نوع استخدام"
                            value={
                                employee.employment_type
                                    ? (employmentLabels[
                                          employee.employment_type
                                      ] ?? employee.employment_type)
                                    : "—"
                            }
                        />
                        <InfoRow
                            label="تاریخ استخدام"
                            value={employee.hire_date ?? "—"}
                        />
                        <InfoRow
                            label="وضعیت اشتغال"
                            value={
                                <Badge
                                    variant={
                                        statusVariants[
                                            employee.employment_status ?? ""
                                        ] ?? "secondary"
                                    }
                                >
                                    {statusLabels[
                                        employee.employment_status ?? ""
                                    ] ?? employee.employment_status}
                                </Badge>
                            }
                        />
                        <InfoRow
                            label="سطح تحصیلات"
                            value={
                                employee.education_level
                                    ? (educationLabels[
                                          employee.education_level
                                      ] ?? employee.education_level)
                                    : "—"
                            }
                        />
                        <InfoRow
                            label="رشته تحصیلی"
                            value={employee.education_field ?? "—"}
                        />
                        <InfoRow
                            label="کاربر مرتبط"
                            value={
                                employee.user
                                    ? `${employee.user.name} (${employee.user.email})`
                                    : "—"
                            }
                        />
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <IconFile className="size-5" />
                                مدارک
                            </CardTitle>
                            <CardDescription>
                                مدارک و مستندات کارمند
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setTrashOpen(true)}
                            >
                                <IconTrash className="size-4" />
                                سطل زباله
                                {trashCount > 0 && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-1 px-1.5 py-0 text-xs"
                                    >
                                        {trashCount}
                                    </Badge>
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleBulkDownload}
                                disabled={isDownloading}
                            >
                                {isDownloading ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconDownload className="size-4" />
                                )}
                                دانلود
                                {selectedIds.length > 0 && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-1 px-1.5 py-0 text-xs"
                                    >
                                        {selectedIds.length}
                                    </Badge>
                                )}
                            </Button>
                            <Button
                                size="sm"
                                onClick={() => setUploadOpen(true)}
                            >
                                <IconFileUpload className="size-4" />
                                آپلود مدرک
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                {downloadError && (
                    <div className="px-6">
                        <p className="text-sm text-destructive">{downloadError}</p>
                    </div>
                )}
                <CardContent>
                    <DocumentSection
                        employeeId={employee.id}
                        personnelCode={employee.personnel_code}
                        showActions={false}
                        selectedIds={selectedIds}
                        onSelectionChange={setSelectedIds}
                    />
                </CardContent>
            </Card>

            <DocumentUploadModal
                employeeId={Number(id)}
                open={uploadOpen}
                onOpenChange={setUploadOpen}
            />
            <DocumentTrashModal
                employeeId={Number(id)}
                open={trashOpen}
                onOpenChange={setTrashOpen}
            />
        </div>
    );
}
