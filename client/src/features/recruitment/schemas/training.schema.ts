import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

export const trainingCourseSchema = z.object({
    course_name: requiredText("نام دوره الزامی است.", 100),
    duration: text(50, "حداکثر ۵۰ کاراکتر."),
    institution: text(100, "حداکثر ۱۰۰ کاراکتر."),
    held_at: text(),
    certificate: text(100, "حداکثر ۱۰۰ کاراکتر."),
});

export type TrainingCourseFormData = z.infer<typeof trainingCourseSchema>;

export const researchSchema = z.object({
    title: requiredText("عنوان تحقیق الزامی است.", 255),
});

export type ResearchFormData = z.infer<typeof researchSchema>;

export const trainingFieldSchema = z.object({
    training_courses: z.array(trainingCourseSchema).optional(),
    professional_memberships: text(1000),
    researches: z.array(researchSchema).optional(),
});

export type TrainingFormData = z.infer<typeof trainingFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    training_courses: z.array(trainingCourseSchema).optional(),
    training_course_item: trainingCourseSchema,
    course_name: requiredText("نام دوره الزامی است.", 100),
    duration: text(50, "حداکثر ۵۰ کاراکتر."),
    institution: text(100, "حداکثر ۱۰۰ کاراکتر."),
    held_at: text(),
    certificate: text(100, "حداکثر ۱۰۰ کاراکتر."),
    researches: z.array(researchSchema).optional(),
    research_item: researchSchema,
    research_title: requiredText("عنوان تحقیق الزامی است.", 255),
    professional_memberships: text(1000, "حداکثر ۱۰۰۰ کاراکتر."),
} as const;
