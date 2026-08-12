import { TrainingSection as QuestionnaireTrainingSection } from "@/features/questionnaire/components/sections/training-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function TrainingSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <QuestionnaireTrainingSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
