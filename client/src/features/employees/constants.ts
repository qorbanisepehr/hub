export const genderLabels: Record<string, string> = {
    male: "مرد",
    female: "زن",
};

export const maritalLabels: Record<string, string> = {
    single: "مجرد",
    married: "متاهل",
};

export const educationLabels: Record<string, string> = {
    diploma: "دیپلم",
    associate: "فوق دیپلم",
    bachelor: "لیسانس",
    master: "فوق لیسانس",
    doctorate: "دکتری",
};

export const employmentLabels: Record<string, string> = {
    official: "رسمی",
    contractual: "قراردادی",
    "project-based": "پروژه‌ای",
};

export const statusLabels: Record<string, string> = {
    active: "فعال",
    inactive: "غیرفعال",
    suspended: "تعلیق",
};

export const statusVariants: Record<string, "default" | "secondary" | "destructive"> = {
    active: "default",
    inactive: "secondary",
    suspended: "destructive",
};
