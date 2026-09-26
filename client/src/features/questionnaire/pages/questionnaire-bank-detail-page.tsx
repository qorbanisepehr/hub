import type { ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "@tanstack/react-router";
import { isAxiosError } from "axios";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorPage } from "@/components/layout";
import { PageHeader } from "@/components/layout";
import { PageLayout } from "@/components/layout";
import { ViewSkeleton } from "@/components/layout";
import { DocumentPrintMenu } from "@/components/shared/document-print-menu";
import { QuestionnaireResumeView } from "@/features/questionnaire/components/questionnaire-resume-view";
import {
    getQuestionnaireDetail,
    fetchQuestionnaireDocument,
} from "@/features/questionnaire/api";
import { questionnaireKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import { toPersianDate } from "@/lib/date-format";
import {
    QUESTIONNAIRE_STATUS_BADGE_VARIANTS,
    QUESTIONNAIRE_STATUS_LABELS,
} from "@/features/questionnaire/constants";

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

export function QuestionnaireBankDetailPage() {
    const { id } = useParams({ from: "/protected/questionnaires/$id" });

    const { data, isLoading, isError, error } = useQuery({
        queryKey: questionnaireKeys.managementDetail(id),
        queryFn: () => getQuestionnaireDetail(Number(id)),
    });

    const questionnaire = data?.data?.data;

    if (isLoading) {
        return <ViewSkeleton columns={1} />;
    }

    if (isError) {
        const status = isAxiosError(error) ? error.response?.status : undefined;

        return (
            <ErrorPage
                status={status}
                title={getApiError(error) ?? undefined}
                homeTo="/questionnaires"
            />
        );
    }

    if (!questionnaire) {
        return (
            <ErrorPage
                status={404}
                title="پرسشنامه مورد نظر یافت نشد"
                homeTo="/questionnaires"
            />
        );
    }

    return (
        <PageLayout>
            <PageHeader
                title={`${questionnaire.first_name} ${questionnaire.last_name}`}
                description={questionnaire.email ?? undefined}
                backTo="/questionnaires"
            >
                <DocumentPrintMenu
                    filenamePrefix={`questionnaire-${questionnaire.uuid}`}
                    fetchDocument={(format) =>
                        fetchQuestionnaireDocument(questionnaire.id, format)
                    }
                />
            </PageHeader>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                QUESTIONNAIRE_STATUS_BADGE_VARIANTS[
                                    questionnaire.status
                                ] ?? "secondary"
                            }
                        >
                            {QUESTIONNAIRE_STATUS_LABELS[questionnaire.status] ??
                                questionnaire.status}
                        </Badge>
                        <Badge variant="outline">
                            نسخه {questionnaire.version}
                        </Badge>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-3">
                        <MetaRow label="موبایل" value={questionnaire.mobile} />
                        <MetaRow
                            label="تاریخ ایجاد"
                            value={toPersianDate(questionnaire.created_at)}
                        />
                        <MetaRow
                            label="آخرین به‌روزرسانی"
                            value={toPersianDate(questionnaire.updated_at)}
                        />
                    </div>
                </CardContent>
            </Card>

            <QuestionnaireResumeView questionnaire={questionnaire} />
        </PageLayout>
    );
}