import type { AuditCategory } from "./types";

export const AUDIT_CATEGORY_LABELS: Record<AuditCategory, string> = {
    auth: "احراز هویت",
    authorization: "مجوزها",
    employee: "کارمند",
    document: "مدرک",
    questionnaire: "پرسشنامه",
    workflow: "گردش کار",
};

export const AUDIT_CATEGORY_VARIANTS: Record<AuditCategory, "default" | "secondary" | "destructive" | "outline"> = {
    auth: "default",
    authorization: "secondary",
    employee: "outline",
    document: "destructive",
    questionnaire: "default",
    workflow: "secondary",
};

export const AUDIT_EVENT_LABELS: Record<string, string> = {
    // Auth
    "auth.login.success": "ورود موفق",
    "auth.login.failed": "ورود ناموفق",
    "auth.logout": "خروج",
    // Authorization
    "authorization.role.assigned": "نقش اعطا شد",
    "authorization.role.removed": "نقش حذف شد",
    "authorization.role.switched": "نقش تغییر کرد",
    // Employee
    "employee.created": "کارمند ایجاد شد",
    "employee.updated": "کارمند بروزرسانی شد",
    "employee.deleted": "کارمند حذف شد",
    // Document
    "document.uploaded": "مدرک بارگذاری شد",
    "document.deleted": "مدرک حذف شد",
    "document.restored": "مدرک بازیابی شد",
    "document.downloaded": "مدرک دانلود شد",
    // Questionnaire
    "questionnaire.submitted": "پرسشنامه ارسال شد",
};

export const AUDIT_ACTOR_TYPE_LABELS: Record<string, string> = {
    user: "کاربر",
    system: "سیستم",
};

export const AUDIT_PER_PAGE_OPTIONS = [15, 25, 50, 100];
