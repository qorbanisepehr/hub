import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { IconHistory, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { AccessGate } from "@/components/shared/access-gate";
import { ErrorPage } from "@/components/shared/error-page";
import { QrCode } from "@/components/shared/qr-code";
import { ShareDialog } from "@/components/shared/share-dialog";
import { getCv } from "@/features/cv/api";
import {
    CV_ENTITY,
    CV_STATUS_BADGE_VARIANTS,
    CV_STATUS_LABELS,
} from "@/features/cv/constants";
import {
    CvFeedbackMenu,
    CvTimelineModal,
} from "@/features/cv/components/cv-lifecycle";
import { cvKeys } from "@/lib/query-keys";
import { CvWizard } from "@/features/cv/components/cv-wizard";
import { CvSuccessPage } from "./cv-success-page";

export function CvFormPage() {
    const { uuid } = useParams({ from: "/public/cv/$uuid" });
    const [shareOpen, setShareOpen] = useState(false);

    const shareUrl = `${window.location.origin}/cv/${uuid}`;

    return (
        <AccessGate
            entity={CV_ENTITY}
            uuid={uuid}
            purpose="edit"
            description="برای ادامه تکمیل رزومه، کد تأیید به شماره موبایل صاحب رزومه ارسال می‌شود."
        >
            <CvFormContent uuid={uuid} shareUrl={shareUrl} />

            <ShareDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                url={shareUrl}
                shareTitle="رزومه"
                shareText="لطفاً فرم رزومه را تکمیل کنید."
            />
        </AccessGate>
    );
}

function CvFormContent({
    uuid,
    shareUrl,
}: {
    uuid: string;
    shareUrl: string;
}) {
    const [shareOpen, setShareOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);

    const { data, isLoading, isError } = useQuery({
        queryKey: cvKeys.detail(uuid),
        queryFn: () => getCv(uuid),
    });

    const cv = data?.data?.data;

    if (cv && (cv.status === "submitted" || cv.status === "approved")) {
        return <CvSuccessPage cv={cv} />;
    }

    return (
        <div className="min-h-dvh bg-background pt-16">
            <div className="mx-auto max-w-4xl px-4 py-8">
                {/* Header */}
                <div className="mb-8 rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex-1 text-start">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold">رزومه</h1>
                                {cv && (
                                    <Badge
                                        variant={
                                            CV_STATUS_BADGE_VARIANTS[
                                                cv.status
                                            ] ?? "secondary"
                                        }
                                    >
                                        {CV_STATUS_LABELS[cv.status] ??
                                            cv.status}
                                    </Badge>
                                )}
                            </div>
                            {cv && (
                                <p className="text-sm text-muted-foreground mt-0.5">
                                    {cv.first_name} {cv.last_name}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            {cv && (
                                <>
                                    <CvFeedbackMenu cv={cv} />

                                    <Button
                                        variant="outline"
                                        size="icon"
                                        aria-label="تاریخچه"
                                        onClick={() => setHistoryOpen(true)}
                                    >
                                        <IconHistory className="size-4" />
                                    </Button>
                                </>
                            )}

                            {cv && (
                                <Button
                                    variant="outline"
                                    onClick={() => setShareOpen(true)}
                                    className="h-24 w-24 p-0 cursor-pointer"
                                >
                                    <QrCode value={shareUrl} size={90} />
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {isLoading && (
                    <div className="flex justify-center py-12">
                        <IconLoader2 className="size-8 animate-spin text-muted-foreground" />
                    </div>
                )}

                {isError && (
                    <ErrorPage
                        title="خطا در بارگذاری رزومه"
                        description="رزومه مورد نظر یافت نشد."
                        homeTo="/"
                    />
                )}

                {cv && <CvWizard cv={cv} />}

                {cv && (
                    <CvTimelineModal
                        cv={cv}
                        open={historyOpen}
                        onOpenChange={setHistoryOpen}
                    />
                )}
            </div>

            {/* Share Modal */}
            <ShareDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                url={shareUrl}
                shareTitle="رزومه"
                shareText="لطفاً فرم رزومه را تکمیل کنید."
            />
        </div>
    );
}
