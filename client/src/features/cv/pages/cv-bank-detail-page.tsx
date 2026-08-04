import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate, useParams } from "@tanstack/react-router";
import {
    IconLoader2,
    IconCheck,
    IconX,
    IconFilePlus,
    IconHistory,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { ErrorPage } from "@/components/shared/error-page";
import {
    getCvBankDetail,
    reviewCv,
    rejectCv,
    createQuestionnaireFromCv,
} from "@/features/cv/api";
import { CV_STATUS_LABELS } from "@/features/cv/constants";
import type { CvLifecycleEvent, CvPersonalInfo, CvContactInfo, CvAdditionalInfo } from "@/features/cv/types";
import { PermissionGuard } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { cvKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";

function DataRow({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm">
                {value || <span className="text-muted-foreground">—</span>}
            </span>
        </div>
    );
}

const EVENT_LABELS: Record<string, string> = {
    submitted: "ارسال",
    reviewed: "تأیید",
    rejected: "بازگشت",
};

function LifecycleList({ lifecycle }: { lifecycle: CvLifecycleEvent[] | null }) {
    if (!lifecycle || lifecycle.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">سابقه‌ای ثبت نشده است</p>
        );
    }

    return (
        <ol className="space-y-3">
            {[...lifecycle].reverse().map((event, i) => (
                <li key={i} className="flex items-start gap-3 text-sm">
                    <Badge variant="outline">
                        {EVENT_LABELS[event.event] ?? event.event}
                    </Badge>
                    <div className="flex-1">
                        <p>
                            نسخه {event.version}
                            {event.reason && (
                                <span className="text-muted-foreground">
                                    {" "}
                                    — {event.reason}
                                </span>
                            )}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {event.at}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

export function CvBankDetailPage() {
    const { id } = useParams({ from: "/protected/cvs/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [rejectOpen, setRejectOpen] = useState(false);
    const [rejectReason, setRejectReason] = useState("");

    const { data, isLoading, isError } = useQuery({
        queryKey: cvKeys.bankDetail(id),
        queryFn: () => getCvBankDetail(id),
    });

    const cv = data?.data?.data;

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: cvKeys.bankDetail(id) });
        queryClient.invalidateQueries({ queryKey: cvKeys.all });
    };

    const reviewMutation = useMutation({
        mutationFn: () => reviewCv(cv!.uuid),
        onSuccess: () => {
            toast.success("رزومه تأیید شد.");
            invalidate();
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const rejectMutation = useMutation({
        mutationFn: () => rejectCv(cv!.uuid, rejectReason),
        onSuccess: () => {
            toast.success("رزومه به پیش‌نویس بازگردانده شد.");
            setRejectOpen(false);
            setRejectReason("");
            invalidate();
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const createQuestionnaireMutation = useMutation({
        mutationFn: () => createQuestionnaireFromCv(cv!.uuid),
        onSuccess: (response) => {
            const questionnaire = response.data?.data;
            toast.success("پرسشنامه پیش‌نویس ایجاد شد.");
            if (questionnaire?.uuid) {
                navigate({
                    to: "/questionnaire/$uuid",
                    params: { uuid: questionnaire.uuid },
                });
            } else {
                invalidate();
            }
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    if (isLoading) {
        return (
            <div className="flex justify-center py-12">
                <IconLoader2 className="size-8 animate-spin text-muted-foreground" />
            </div>
        );
    }

    if (isError || !cv) {
        return (
            <ErrorPage
                title="خطا در بارگذاری رزومه"
                description="رزومه مورد نظر یافت نشد."
                homeTo="/cvs"
            />
        );
    }

    const pi: Partial<CvPersonalInfo> = cv.personal_info ?? {};
    const ci: Partial<CvContactInfo> = cv.contact_info ?? {};
    const additional: Partial<CvAdditionalInfo> = cv.additional_info ?? {};

    return (
        <div className="space-y-6">
            {/* ── Header / actions ── */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold">
                                    {cv.first_name} {cv.last_name}
                                </h1>
                                <Badge>
                                    {CV_STATUS_LABELS[cv.status] ?? cv.status}
                                </Badge>
                                <Badge variant="outline">
                                    نسخه {cv.version}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                {cv.email ?? ""}
                            </p>
                        </div>

                        {cv.status === "submitted" && (
                            <div className="flex flex-wrap gap-2">
                                <PermissionGuard
                                    permission={PERMISSIONS.CV_CREATE_QUESTIONNAIRE}
                                >
                                    <Button
                                        variant="outline"
                                        disabled={
                                            createQuestionnaireMutation.isPending
                                        }
                                        onClick={() =>
                                            createQuestionnaireMutation.mutate()
                                        }
                                    >
                                        {createQuestionnaireMutation.isPending ? (
                                            <IconLoader2 className="size-4 animate-spin" />
                                        ) : (
                                            <IconFilePlus className="size-4" />
                                        )}
                                        ایجاد پرسشنامه پیش‌نویس
                                    </Button>
                                </PermissionGuard>

                                <PermissionGuard
                                    permission={PERMISSIONS.CV_REJECT}
                                >
                                    <Button
                                        variant="destructive"
                                        disabled={rejectMutation.isPending}
                                        onClick={() => setRejectOpen(true)}
                                    >
                                        <IconX className="size-4" />
                                        بازگرداندن
                                    </Button>
                                </PermissionGuard>

                                <PermissionGuard
                                    permission={PERMISSIONS.CV_REVIEW}
                                >
                                    <Button
                                        disabled={
                                            reviewMutation.isPending ||
                                            createQuestionnaireMutation.isPending
                                        }
                                        onClick={() => reviewMutation.mutate()}
                                    >
                                        {reviewMutation.isPending ? (
                                            <IconLoader2 className="size-4 animate-spin" />
                                        ) : (
                                            <IconCheck className="size-4" />
                                        )}
                                        تأیید رزومه
                                    </Button>
                                </PermissionGuard>
                            </div>
                        )}
                    </div>

                    <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t">
                        <DataRow label="موبایل" value={cv.mobile} />
                        <DataRow label="تاریخ ایجاد" value={cv.created_at} />
                        <DataRow label="آخرین به‌روزرسانی" value={cv.updated_at} />
                    </div>
                </CardContent>
            </Card>

            {/* ── مشخصات فردی ── */}
            <Card>
                <CardHeader>
                    <CardTitle>مشخصات فردی</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DataRow label="جنسیت" value={pi.gender} />
                        <DataRow label="تاریخ تولد" value={pi.birth_date} />
                        <DataRow label="محل تولد" value={pi.birth_place} />
                        <DataRow label="کد ملی" value={pi.national_id} />
                        <DataRow
                            label="شماره شناسنامه"
                            value={pi.birth_certificate_number}
                        />
                        <DataRow label="وضعیت تأهل" value={pi.marital_status} />
                    </div>
                </CardContent>
            </Card>

            {/* ── اطلاعات تماس ── */}
            <Card>
                <CardHeader>
                    <CardTitle>اطلاعات تماس</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DataRow label="تلفن ثابت" value={ci.phone} />
                        <DataRow label="تلفن اضطراری" value={ci.emergency_phone} />
                        {ci.address && (
                            <>
                                <DataRow label="استان" value={ci.address.province} />
                                <DataRow label="شهر" value={ci.address.city} />
                                <DataRow
                                    label="کد پستی"
                                    value={ci.address.postal_code}
                                />
                                <DataRow label="آدرس" value={ci.address.address} />
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── اطلاعات تکمیلی ── */}
            <Card>
                <CardHeader>
                    <CardTitle>اطلاعات تکمیلی</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <DataRow label="علاقه‌مندی‌ها" value={additional.hobbies} />
                        <DataRow
                            label="نقاط قوت"
                            value={additional.strengths_and_improvements}
                        />
                    </div>
                    {additional.references?.length ? (
                        <div className="mt-4 pt-4 border-t space-y-2">
                            {additional.references.map((ref, i) => (
                                <div
                                    key={i}
                                    className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm p-2 rounded bg-muted/50"
                                >
                                    <DataRow label="نام" value={ref.full_name} />
                                    <DataRow label="رابطه" value={ref.relationship} />
                                    <DataRow
                                        label="تلفن"
                                        value={ref.workplace_phone}
                                    />
                                </div>
                            ))}
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            {/* ── چرخه حیات ── */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <IconHistory className="size-4" />
                        چرخه حیات رزومه
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <LifecycleList lifecycle={cv.lifecycle} />
                </CardContent>
            </Card>

            {/* ── Reject dialog ── */}
            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>بازگرداندن رزومه</DialogTitle>
                        <DialogDescription>
                            رزومه به وضعیت پیش‌نویس بازگردانده می‌شود. دلیل
                            بازگرداندن الزامی است.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="reject-reason">دلیل</Label>
                        <Input
                            id="reject-reason"
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            placeholder="مثلاً: اطلاعات ناقص است"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setRejectOpen(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={
                                rejectMutation.isPending ||
                                rejectReason.trim().length === 0
                            }
                            onClick={() => rejectMutation.mutate()}
                        >
                            {rejectMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconX className="size-4" />
                            )}
                            بازگرداندن
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
