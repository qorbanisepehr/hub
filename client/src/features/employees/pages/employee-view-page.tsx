import * as React from "react";
import { Link, useNavigate, useParams } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { saveAs } from "file-saver";
import {
    IconDownload,
    IconFile,
    IconFileUpload,
    IconLoader2,
    IconPencil,
    IconSettings,
    IconTrash,
    IconUser,
} from "@tabler/icons-react";
import { toast } from "sonner";

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
import { fetchEmployee, deleteEmployee } from "@/features/employees/api";
import { fetchTrashedDocuments, bulkDownloadDocuments } from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";
import { DocumentSection } from "@/features/documents/components/document-section";
import { DocumentUploadModal } from "@/features/documents/components/document-upload-modal";
import { DocumentTrashModal } from "@/features/documents/components/document-trash-modal";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { ViewSkeleton } from "@/components/shared/view-skeleton";
import { InfoRow } from "@/components/shared/info-row";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { employeeKeys } from "@/lib/query-keys";

import {
    genderLabels,
    maritalLabels,
    educationLabels,
    employmentLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";

export function EmployeeViewPage() {
    const { id } = useParams({ from: "/protected/employees/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [uploadOpen, setUploadOpen] = React.useState(false);
    const [trashOpen, setTrashOpen] = React.useState(false);
    const [selectedIds, setSelectedIds] = React.useState<number[]>([]);
    const [isDownloading, setIsDownloading] = React.useState(false);
    const [downloadError, setDownloadError] = React.useState<string | null>(null);
    const employeeId = Number(id);

    const { data: employee, isLoading } = useQuery({
        queryKey: employeeKeys.detail(employeeId),
        queryFn: async () => {
            const { data } = await fetchEmployee(employeeId);
            return data.data;
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteEmployee(employeeId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.all });
            toast.success("کارمند حذف شد");
            navigate({ to: "/employees" });
        },
        onError: () => {
            toast.error("خطا در حذف کارمند");
        },
    });

    const { data: trashedDocuments } = useQuery({
        queryKey: employeeKeys.documentTrash(employeeId),
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(employeeId);
            return data.data;
        },
    });

    const trashCount = trashedDocuments?.length ?? 0;

    async function handleBulkDownload() {
        setIsDownloading(true);
        setDownloadError(null);
        try {
            const response = await bulkDownloadDocuments(
                employeeId,
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
        return <ViewSkeleton leftRows={6} rightRows={6} />;
    }

    if (!employee) {
        return (
            <ErrorPage
                status={404}
                title="کارمند مورد نظر یافت نشد"
                homeTo="/employees"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    return (
        <PageLayout>
            <PageHeader
                title={`${employee.first_name} ${employee.last_name}`}
                description={`کد پرسنلی: ${employee.personnel_code}`}
                backTo="/employees"
            >
                <div className="flex items-center gap-2">
                    <PermissionGuard permission={["employee.update_own", "employee.update_all"]}>
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
                    </PermissionGuard>
                    <PermissionGuard permission="employee.delete">
                        <ConfirmDeleteButton
                            onConfirm={() => deleteMutation.mutate()}
                            isPending={deleteMutation.isPending}
                        />
                    </PermissionGuard>
                </div>
            </PageHeader>

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
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconUser className="size-5" />
                        کاربر سیستمی مرتبط
                    </CardTitle>
                    <CardDescription>
                        اطلاعات حساب کاربری متصل به این کارمند
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {employee.user ? (
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="divide-y">
                                <InfoRow
                                    label="نام"
                                    value={employee.user.name}
                                />
                                <InfoRow
                                    label="ایمیل"
                                    value={
                                        <span dir="ltr">
                                            {employee.user.email}
                                        </span>
                                    }
                                />
                                <InfoRow
                                    label="تلفن"
                                    value={employee.user.phone ?? "—"}
                                />
                            </div>
                            <div className="divide-y">
                                <InfoRow
                                    label="نام کاربری"
                                    value={
                                        <span dir="ltr">
                                            {employee.user.username ?? "—"}
                                        </span>
                                    }
                                />
                                <InfoRow
                                    label="نقش فعال"
                                    value={
                                        employee.user.active_role ? (
                                            <Badge variant="secondary">
                                                {employee.user.active_role.display_name}
                                            </Badge>
                                        ) : (
                                            "—"
                                        )
                                    }
                                />
                                <div className="flex items-center justify-end gap-2 py-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        nativeButton={false}
                                        render={
                                            <Link
                                                to="/users/$userId"
                                                params={{ userId: String(employee.user!.id) }}
                                            />
                                        }
                                    >
                                        <IconUser className="size-4" />
                                        مشاهده کاربر
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        nativeButton={false}
                                        render={
                                            <Link
                                                to="/users/$userId/roles"
                                                params={{ userId: String(employee.user!.id) }}
                                            />
                                        }
                                    >
                                        <IconSettings className="size-4" />
                                        مدیریت نقش‌ها
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                            <IconUser className="mb-2 size-8 opacity-40" />
                            <p className="text-sm">کاربر سیستمی متصل نیست</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                از صفحه ویرایش کارمند می‌توانید کاربر مرتبط را انتخاب کنید
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

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
                            <PermissionGuard permission={["document.delete_own", "document.delete_all"]}>
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
                            </PermissionGuard>
                            <PermissionGuard permission={["document.download_own", "document.download_all"]}>
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
                            </PermissionGuard>
                            <PermissionGuard permission={["document.upload_own", "document.upload_all"]}>
                                <Button
                                    size="sm"
                                    onClick={() => setUploadOpen(true)}
                                >
                                    <IconFileUpload className="size-4" />
                                    آپلود مدرک
                                </Button>
                            </PermissionGuard>
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
                employeeId={employeeId}
                open={uploadOpen}
                onOpenChange={setUploadOpen}
            />
            <DocumentTrashModal
                employeeId={employeeId}
                open={trashOpen}
                onOpenChange={setTrashOpen}
            />
        </PageLayout>
    );
}
