import { SkillsSection as QuestionnaireSkillsSection } from "@/features/questionnaire/components/sections/skills-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function SkillsSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <QuestionnaireSkillsSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
