import { useState } from "react";
import type { ReactNode } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import {
    IconCheck,
    IconDownload,
    IconFilePlus,
    IconHistory,
    IconLoader2,
    IconShare,
    IconX,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageLayout } from "@/components/shared/page-layout";
import { ViewSkeleton } from "@/components/shared/view-skeleton";
import { ShareDialog } from "@/components/shared/share-dialog";
import { CvResumeView } from "@/features/cv/components/cv-resume-view";
import {
    CvFeedbackMenu,
    CvTimelineModal,
} from "@/features/cv/components/cv-lifecycle";
import {
    approveCv,
    createQuestionnaireFromCv,
    getCvBankDetail,
    rejectCv,
} from "@/features/cv/api";
import {
    CV_STATUS_BADGE_VARIANTS,
    CV_STATUS_LABELS,
} from "@/features/cv/constants";
import { cvKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import { toPersianDate } from "@/lib/date-format";
import { getUserDisplayName } from "@/lib/user-display";

function MetaRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm">
                {value || <span className="text-muted-foreground">—</span>}
            </span>
        </div>
    );
}

export function CvBankDetailPage() {
    const { id } = useParams({ from: "/protected/cvs/$id" });
    const queryClient = useQueryClient();
    const [rejectOpen, setRejectOpen] = useState(false);
    const [rejectReason, setRejectReason] = useState("");
    const [historyOpen, setHistoryOpen] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const [shareQuestionnaireUuid, setShareQuestionnaireUuid] = useState<
        string | null
    >(null);

    const { data, isLoading, isError } = useQuery({
        queryKey: cvKeys.bankDetail(id),
        queryFn: () => getCvBankDetail(id),
    });

    const cv = data?.data?.data;

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: cvKeys.bankDetail(id) });
        queryClient.invalidateQueries({ queryKey: cvKeys.all });
    };

    const approveMutation = useMutation({
        mutationFn: () => approveCv(cv!.uuid),
        onSuccess: () => {
            toast.success("رزومه تأیید شد.");
            invalidate();
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    const rejectMutation = useMutation({
        mutationFn: () => rejectCv(cv!.uuid, rejectReason),
        onSuccess: () => {
            toast.success("رزومه رد شد.");
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
            toast.success("پرسشنامه پیش‌نویس ایجاد شد و رزومه تأیید شد.");
            invalidate();
            if (questionnaire?.uuid) {
                setShareQuestionnaireUuid(questionnaire.uuid);
                setShareOpen(true);
            }
        },
        onError: (err) => toast.error(getApiError(err)),
    });

    if (isLoading) {
        return <ViewSkeleton columns={1} />;
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

    const canForward = cv.status === "submitted" || cv.status === "approved";
    const linkedQuestionnaire = cv.questionnaire?.uuid ?? null;

    const openShare = (uuid: string) => {
        setShareQuestionnaireUuid(uuid);
        setShareOpen(true);
    };

    return (
        <PageLayout>
            <PageHeader
                title={`${cv.first_name} ${cv.last_name}`}
                description={cv.email ?? undefined}
                backTo="/cvs"
            >
                <div className="flex flex-wrap items-center gap-2">
                    <CvFeedbackMenu cv={cv} />

                    {cv.resume_document?.download_url && (
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={cv.resume_document.download_url}
                                    aria-label="دانلود رزومه"
                                />
                            }
                        >
                            <IconDownload className="size-4" />
                            دانلود رزومه
                        </Button>
                    )}

                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="تاریخچه"
                        onClick={() => setHistoryOpen(true)}
                    >
                        <IconHistory className="size-4" />
                    </Button>

                    {canForward &&
                        !linkedQuestionnaire &&
                        cv.capabilities.create_questionnaire && (
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
                                ایجاد پرسشنامه
                            </Button>
                        )}

                    {linkedQuestionnaire && (
                        <Button
                            variant="outline"
                            onClick={() => openShare(linkedQuestionnaire)}
                        >
                            <IconShare className="size-4" />
                            اشتراک‌گذاری
                        </Button>
                    )}

                    {canForward && !linkedQuestionnaire && cv.capabilities.reject && (
                        <Button
                            variant="destructive"
                            disabled={rejectMutation.isPending}
                            onClick={() => setRejectOpen(true)}
                        >
                            <IconX className="size-4" />
                            رد رزومه
                        </Button>
                    )}

                    {cv.status === "submitted" && cv.capabilities.approve && (
                        <Button
                            disabled={
                                approveMutation.isPending ||
                                createQuestionnaireMutation.isPending
                            }
                            onClick={() => approveMutation.mutate()}
                        >
                            {approveMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconCheck className="size-4" />
                            )}
                            تأیید رزومه
                        </Button>
                    )}
                </div>
            </PageHeader>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                CV_STATUS_BADGE_VARIANTS[cv.status] ??
                                "secondary"
                            }
                        >
                            {CV_STATUS_LABELS[cv.status] ?? cv.status}
                        </Badge>
                        <Badge variant="outline">
                            نسخه {cv.version}
                        </Badge>
                        {cv.reviewer && (
                            <span className="text-xs text-muted-foreground">
                                تأیید توسط {getUserDisplayName(cv.reviewer)}
                                {cv.reviewer.role
                                    ? ` (${cv.reviewer.role})`
                                    : ""}
                            </span>
                        )}
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-3">
                        <MetaRow label="موبایل" value={cv.mobile} />
                        <MetaRow
                            label="تاریخ ایجاد"
                            value={toPersianDate(cv.created_at)}
                        />
                        <MetaRow
                            label="آخرین به‌روزرسانی"
                            value={toPersianDate(cv.updated_at)}
                        />
                    </div>
                </CardContent>
            </Card>

            <CvResumeView cv={cv} documents={cv.documents ?? []} />

            <CvTimelineModal
                cv={cv}
                open={historyOpen}
                onOpenChange={setHistoryOpen}
            />

            <ResponsiveDialog
                open={rejectOpen}
                onOpenChange={setRejectOpen}
                title="رد رزومه"
                description="رزومه به وضعیت «رد شده» منتقل می‌شود و تا زمان ویرایش مجدد توسط داوطلب در بانک رزومه مشخص می‌ماند. دلیل رد الزامی است."
                footer={
                    <>
                        <Button
                            variant="ghost"
                            onClick={() => setRejectOpen(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            disabled={
                                rejectMutation.isPending ||
                                rejectReason.trim().length === 0
                            }
                            onClick={() => rejectMutation.mutate()}
                        >
                            {rejectMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconCheck className="size-4" />
                            )}
                            رد رزومه
                        </Button>
                    </>
                }
            >
                <div className="space-y-2">
                    <Label htmlFor="reject-reason">دلیل</Label>
                    <Input
                        id="reject-reason"
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        placeholder="مثلاً: اطلاعات ناقص است"
                    />
                </div>
            </ResponsiveDialog>

            {shareQuestionnaireUuid && (
                <ShareDialog
                    open={shareOpen}
                    onOpenChange={setShareOpen}
                    title="اشتراک‌گذاری پرسشنامه"
                    url={`${window.location.origin}/questionnaire/${shareQuestionnaireUuid}`}
                />
            )}
        </PageLayout>
    );
}
