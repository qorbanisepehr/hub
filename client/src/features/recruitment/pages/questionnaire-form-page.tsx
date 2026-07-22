import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { IconLoader2, IconShare } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ErrorPage } from "@/components/shared/error-page";
import { QrCode } from "@/components/shared/qr-code";
import { ShareDialog } from "@/components/shared/share-dialog";
import { getQuestionnaire } from "@/features/recruitment/api";
import { QuestionnaireWizard } from "@/features/recruitment/components/questionnaire-wizard";
import { QuestionnaireSuccessPage } from "./questionnaire-success-page";

export function QuestionnaireFormPage() {
    const { uuid } = useParams({ from: "/public/questionnaire/$uuid" });
    const [shareOpen, setShareOpen] = useState(false);

    const shareUrl = `${window.location.origin}/questionnaire/${uuid}`;

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
                    <div className="flex items-center gap-4">
                        {/* Share button — right side (start in RTL) */}
                        <Button
                            variant="outline"
                            size="icon-sm"
                            onClick={() => setShareOpen(true)}
                        >
                            <IconShare className="size-4" />
                        </Button>

                        {/* Title — center */}
                        <div className="flex-1 text-center">
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

                        {/* QR code — left side (end in RTL) */}
                        {questionnaire && (
                            <div className="rounded-lg border bg-background">
                                <QrCode value={shareUrl} size={90} />
                            </div>
                        )}
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
                    <QuestionnaireWizard questionnaire={questionnaire} />
                )}
            </div>

            {/* Share Modal */}
            <ShareDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                url={shareUrl}
            />
        </div>
    );
}
