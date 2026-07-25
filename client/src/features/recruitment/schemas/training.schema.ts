import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const trainingCourseSchema = z.object({
    course_name: requiredString.max(100, "حداکثر ۱۰۰ کاراکتر."),
    duration: z.string().max(50).optional(),
    institution: z.string().max(100).optional(),
    held_at: z.string().optional(),
    certificate: z.string().max(100).optional().nullable(),
});

export type TrainingCourseFormData = z.infer<typeof trainingCourseSchema>;

export const researchSchema = z.object({
    title: requiredString.max(255, "حداکثر ۲۵۵ کاراکتر."),
});

export type ResearchFormData = z.infer<typeof researchSchema>;

export const trainingFieldSchema = z.object({
    training_courses: z.array(trainingCourseSchema).optional(),
    professional_memberships: z.string().max(1000).optional(),
    researches: z.array(researchSchema).optional(),
});

export type TrainingFormData = z.infer<typeof trainingFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    training_courses: z.array(trainingCourseSchema).optional(),
    training_course_item: trainingCourseSchema,
    researches: z.array(researchSchema).optional(),
    research_item: researchSchema,
    professional_memberships: z.string().max(1000).optional(),
} as const;
