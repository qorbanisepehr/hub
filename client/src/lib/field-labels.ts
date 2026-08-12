/**
 * Persian field labels for the "بررسی اعتبار" summary. Keyed by the tail
 * segment of the TanStack field name (e.g. `education.student_degree` → the
 * tail `student_degree`), so a single map serves both nested object paths and
 * repeater rows.
 */
const FIELD_LABELS: Record<string, string> = {
    // Identity / top-level
    first_name: "نام",
    last_name: "نام خانوادگی",
    email: "ایمیل",
    mobile: "شماره موبایل",
    gender: "جنسیت",
    blood_group: "گروه خونی",
    birth_date: "تاریخ تولد",
    birth_place: "محل تولد",
    birth_certificate_number: "شماره شناسنامه",
    father_name: "نام پدر",
    religion: "مذهب",
    marital_status: "وضعیت تأهل",
    spouse_employment_status: "وضعیت اشتغال همسر",
    spouse_job: "شغل همسر",
    military_status: "وضعیت نظام وظیفه",
    id_number: "کد ملی",
    first_name_en: "نام انگلیسی",
    last_name_en: "نام خانوادگی انگلیسی",
    dependents_count: "تعداد افراد تحت تکفل",
    children_count: "تعداد فرزندان",

    // Contact / address
    phone: "تلفن ثابت",
    emergency_phone: "تلفن اضطراری",
    postal_code: "کد پستی",
    province: "استان",
    city: "شهر",
    address: "آدرس",
    plaque: "پلاک",
    floor: "طبقه",
    unit: "واحد",

    // Education
    education_records: "سوابق تحصیلی",
    degree: "مدرک",
    field: "رشته تحصیلی",
    institution: "دانشگاه",
    location: "محل",
    from: "از تاریخ",
    to: "تا تاریخ",
    thesis_title: "عنوان پایان‌نامه",
    graduation_date: "تاریخ فارغ‌التحصیلی",
    gpa: "معدل",
    is_student: "وضعیت دانشجویی",
    student_degree: "مقطع تحصیلی",
    student_field: "رشته تحصیلی",
    student_university: "دانشگاه",
    student_country: "کشور",
    student_city: "شهر",
    student_semester: "ترم فعلی",
    passed_units: "تعداد واحدهای گذرانده",
    remaining_units: "تعداد واحدهای باقی‌مانده",
    student_gpa: "معدل",
    study_start: "تاریخ شروع تحصیل",
    expected_graduation: "تاریخ انتظار فارغ‌التحصیلی",
    thesis_submitted: "وضعیت پایان‌نامه",
    student_thesis_title: "عنوان پایان‌نامه",
    free_days_per_week: "روزهای آزاد در هفته",
    education_description: "توضیحات تحصیلی",

    // Work experience
    work_experiences: "سوابق شغلی",
    company: "نام شرکت",
    position: "سمت شغلی",
    industry: "صنعت",
    contract_type: "نوع قرارداد",
    last_salary: "آخرین حقوق",
    leave_reason: "دلیل ترک",
    manager_name: "نام مدیر",
    achievements: "دستاوردها",
    allow_contact_previous_managers: "اجازه تماس با مدیران قبلی",
    contact_restriction_description: "توضیحات محدودیت تماس",

    // Skills
    languages: "زبان‌ها",
    language: "نام زبان",
    reading: "سطح خواندن",
    writing: "سطح نوشتن",
    speaking: "سطح صحبت کردن",
    comprehension: "سطح درک مطلب",
    software_skills: "مهارت‌های نرم‌افزاری",
    specialized: "نرم‌افزارهای تخصصی",
    general: "نرم‌افزارهای عمومی",
    name: "نام نرم‌افزار",
    level: "سطح مهارت",
    certificates: "گواهینامه‌ها",
    title: "عنوان",
    expire_at: "تاریخ انقضا",
    special_skills: "مهارت‌های خاص",
    value: "مهارت",

    // Training
    training_courses: "دوره‌های آموزشی",
    course_name: "نام دوره",
    duration: "مدت دوره",
    held_at: "تاریخ برگزاری",
    certificate: "مدرک دوره",
    researches: "تحقیقات و پژوهش‌ها",
    professional_memberships: "عضویت‌های حرفه‌ای",

    // Additional info
    has_chronic_disease: "بیماری مزمن",
    chronic_disease_description: "توضیحات بیماری مزمن",
    has_major_surgery: "عمل جراحی سنگین",
    major_surgery_description: "توضیحات جراحی",
    has_disability: "معلولیت",
    disability_description: "توضیحات معلولیت",
    can_travel: "امکان سفر",
    travel_description: "توضیحات سفر",
    has_criminal_record: "سوءسابقه کیفری",
    criminal_record_description: "توضیحات سوءسابقه",
    reason_for_joining: "دلیل تمایل به همکاری",
    company_introduction_method: "نحوه آشنایی با شرکت",
    hobbies: "علاقه‌مندی‌ها",
    references: "ارجاعات",
    full_name: "نام و نام خانوادگی",
    relationship: "رابطه",
    workplace_phone: "تلفن محل کار",
    strengths_and_improvements: "نقاط قوت و زمینه‌های قابل بهبود",

    // Job request
    employment_type: "نوع اشتغال",
    expected_monthly_salary: "حقوق ماهانه مورد انتظار",
    minimum_hours_per_month: "حداقل ساعات کاری در ماه",
    expected_hourly_salary: "حقوق ساعتی مورد انتظار",
    submitted_resume_before: "ارسال رزومه قبلی",
    interviewed_before: "مصاحبه قبلی",
    other_information: "سایر اطلاعات",
    accept_information: "تأیید اطلاعات",
    preferred_workplace: "محل کار مورد نظر",
    job_priority_1: "اولویت شغلی ۱",
    job_priority_2: "اولویت شغلی ۲",
    currently_employed: "شاغل در حال حاضر",
    available_start_date: "تاریخ شروع به کار",

    // Military service
    status: "وضعیت",
    organization: "سازمان",
    reason: "دلیل",
};

/**
 * Human-readable reference for a field error. Adds the record row number for
 * repeater paths (e.g. `education.education_records.1.degree` → «مدرک (ردیف
 * ۲)») and falls back to the raw tail when no Persian label is known.
 */
export function describeField(fieldName: string): string {
    const segments = fieldName.split(".");
    const tail = segments[segments.length - 1] ?? fieldName;
    const recordSegment = segments.find((segment) => /^\d+$/.test(segment));
    const label = FIELD_LABELS[tail] ?? tail;

    if (recordSegment !== undefined) {
        const row = (Number(recordSegment) + 1).toLocaleString("fa-IR");
        return `${label} (ردیف ${row})`;
    }
    return label;
}
