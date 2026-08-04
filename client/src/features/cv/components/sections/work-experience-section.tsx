import { WorkExperienceSection as RecruitmentWorkExperienceSection } from "@/features/recruitment/components/sections/work-experience-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function WorkExperienceSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <RecruitmentWorkExperienceSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
