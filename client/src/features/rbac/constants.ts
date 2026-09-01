export const MATRIX_MANAGER_TYPES = {
    project: "مدیر پروژه",
    functional: "مدیر عملکردی",
    technical: "مدیر فنی",
} as const;

export type MatrixManagerType = keyof typeof MATRIX_MANAGER_TYPES;


export const MATRIX_MANAGER_TYPES_KEYS = [
    "project",
    "functional",
    "technical",
] as const;
export const ROLE_TYPES = {
    system: "سیستمی",
    organization: "سازمانی",
} as const;

export type RoleType = keyof typeof ROLE_TYPES;

export const EDUCATION_LEVELS = {
    diploma: "دیپلم",
    associate: "فوق دیپلم",
    bachelor: "لیسانس",
    master: "فوق لیسانس",
    doctorate: "دکتری",
} as const;

export type EducationLevel = keyof typeof EDUCATION_LEVELS;

export const EDUCATION_LEVELS_KEYS = [
    "diploma",
    "associate",
    "bachelor",
    "master",
    "doctorate",
] as const;
