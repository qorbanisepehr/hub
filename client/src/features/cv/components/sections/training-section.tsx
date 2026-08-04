import { TrainingSection as RecruitmentTrainingSection } from "@/features/recruitment/components/sections/training-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function TrainingSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <RecruitmentTrainingSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
