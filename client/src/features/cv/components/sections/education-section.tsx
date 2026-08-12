import { EducationSection as QuestionnaireEducationSection } from "@/features/questionnaire/components/sections/education-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function EducationSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <QuestionnaireEducationSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
