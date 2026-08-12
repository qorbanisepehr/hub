import { WorkExperienceSection as QuestionnaireWorkExperienceSection } from "@/features/questionnaire/components/sections/work-experience-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function WorkExperienceSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <QuestionnaireWorkExperienceSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
