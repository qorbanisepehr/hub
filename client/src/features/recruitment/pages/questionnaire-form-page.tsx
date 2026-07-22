import { useQuery } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { IconLoader2 } from "@tabler/icons-react";

import { ErrorPage } from "@/components/shared/error-page";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { getQuestionnaire } from "@/features/recruitment/api";
import { QuestionnaireWizard } from "@/features/recruitment/components/questionnaire-wizard";

export function QuestionnaireFormPage() {
    const { uuid } = useParams({ from: "/public/questionnaire/$uuid" });

    const { data, isLoading, isError } = useQuery({
        queryKey: ["questionnaire", uuid],
        queryFn: () => getQuestionnaire(uuid),
    });

    const questionnaire = data?.data?.data;

    return (
        <div className="min-h-screen bg-background">
            <div className="mx-auto max-w-4xl px-4 py-8">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold">پرسشنامه استخدامی</h1>
                    {questionnaire && (
                        <p className="text-muted-foreground mt-1">
                            {questionnaire.first_name} {questionnaire.last_name}
                        </p>
                    )}
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

                {questionnaire && <QuestionnaireWizard questionnaire={questionnaire} />}
            </div>
        </div>
    );
}
