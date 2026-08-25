export const MATRIX_MANAGER_TYPES = {
    project: "مدیر پروژه",
    functional: "مدیر عملکردی",
    technical: "مدیر فنی",
} as const;

export type MatrixManagerType = keyof typeof MATRIX_MANAGER_TYPES;

export const EDUCATION_LEVELS = {
    diploma: "دیپلم",
    associate: "فوق دیپلم",
    bachelor: "لیسانس",
    master: "فوق لیسانس",
    doctorate: "دکتری",
} as const;

export type EducationLevel = keyof typeof EDUCATION_LEVELS;
