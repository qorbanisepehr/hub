const FORM_OPTION_GROUP_LABELS: Record<string, string> = {
    gender: "جنسیت",
    blood_group: "گروه خونی",
    marital_status: "وضعیت تأهل",
    spouse_employment_status: "وضعیت اشتغال همسر",
    military_status: "وضعیت نظام وظیفه",
    employment_type: "نوع همکاری",
    preferred_workplace: "محل کار ترجیحی",
    religion: "دین",
    religion_sect: "مذهب",
    cooperation_type: "نوع همکاری مورد نظر",
    insurance_type: "نوع بیمه",
    physical_condition: "وضعیت جسمانی",
    property_status: "وضعیت ملک",
    seniority_level: "سطح ارشدیت",
    contract_type: "نوع قرارداد",
    termination_reason: "علت قطع همکاری",
    language: "زبان",
    country: "کشور",
    introduction_method: "نحوه آشنایی",
    degree: "مدرک تحصیلی",
    disability_type: "نوع معلولیت",
    organizational_rank: "رده سازمانی",
    province: "استان",
    county: "شهرستان",
    district: "بخش",
    city: "شهر",
    rural_district: "دهستان",
    village: "روستا",
};

export function groupDisplayName(
    group: string,
    groupLabel?: string | null,
): string {
    return FORM_OPTION_GROUP_LABELS[group] ?? groupLabel ?? group;
}
