import { EducationSection as RecruitmentEducationSection } from "@/features/recruitment/components/sections/education-section";
import type { CvFormApi } from "@/features/cv/types";

type SectionProps = {
    form: CvFormApi;
    uuid?: string;
    onPersist?: () => void;
};

export function EducationSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <RecruitmentEducationSection
            form={form}
            uuid={uuid}
            onPersist={onPersist}
            entity="cv"
        />
    );
}
