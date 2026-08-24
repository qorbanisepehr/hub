import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { IconLoader2, IconShare } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { AccessGate } from "@/components/shared/access-gate";
import { ErrorPage } from "@/components/layout";
import { QrCode } from "@/components/shared/qr-code";
import { ShareDialog } from "@/components/shared/share-dialog";
import { getQuestionnaire } from "@/features/questionnaire/api";
import { QuestionnaireWizard } from "@/features/questionnaire/components/questionnaire-wizard";
import { QuestionnaireSuccessPage } from "./questionnaire-success-page";

export function QuestionnaireFormPage() {
    const { uuid } = useParams({ from: "/public/questionnaire/$uuid" });
    const [shareOpen, setShareOpen] = useState(false);

    const shareUrl = `${window.location.origin}/questionnaire/${uuid}`;

    return (
        <AccessGate
            entity="questionnaire"
            uuid={uuid}
            purpose="edit"
            description="برای ادامه تکمیل پرسشنامه، کد تأیید به شماره موبایل صاحب پرسشنامه ارسال می‌شود."
        >
            <QuestionnaireFormContent uuid={uuid} shareUrl={shareUrl} />

            <ShareDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                url={shareUrl}
                shareTitle="پرسشنامه استخدامی"
                shareText="لطفاً فرم پرسشنامه را تکمیل کنید."
            />
        </AccessGate>
    );
}

function QuestionnaireFormContent({
    uuid,
    shareUrl,
}: {
    uuid: string;
    shareUrl: string;
}) {
    const [shareOpen, setShareOpen] = useState(false);

    const { data, isLoading, isError } = useQuery({
        queryKey: ["questionnaire", uuid],
        queryFn: () => getQuestionnaire(uuid),
    });

    const questionnaire = data?.data?.data;

    if (questionnaire && questionnaire.status === "submitted") {
        return <QuestionnaireSuccessPage />;
    }

    return (
        <div className="min-h-dvh bg-background pt-16">
            <div className="mx-auto max-w-4xl px-4 py-8">
                {/* Header */}
                <div className="mb-8 rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex-1 text-start">
                            <h1 className="text-xl font-bold">
                                پرسشنامه استخدامی
                            </h1>
                            {questionnaire && (
                                <p className="text-sm text-muted-foreground mt-0.5">
                                    {questionnaire.first_name}{" "}
                                    {questionnaire.last_name}
                                </p>
                            )}
                        </div>

                        <div className="">
                            {questionnaire && (
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
                        title="خطا در بارگذاری پرسشنامه"
                        description="پرسشنامه مورد نظر یافت نشد."
                        homeTo="/"
                    />
                )}

                {questionnaire && (
                    <QuestionnaireWizard
                        key={questionnaire.uuid}
                        questionnaire={questionnaire}
                    />
                )}
            </div>

            {/* Share Modal */}
            <ShareDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                url={shareUrl}
                shareTitle="پرسشنامه استخدامی"
                shareText="لطفاً فرم پرسشنامه را تکمیل کنید."
            />
        </div>
    );
}
