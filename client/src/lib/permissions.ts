export const PERMISSIONS = {
    // User
    USER_VIEW: "user.view",
    USER_CREATE: "user.create",
    USER_UPDATE: "user.update",
    USER_DELETE: "user.delete",
    USER_ASSIGN_ROLES: "user.assign-roles",

    // Role
    ROLE_VIEW: "role.view",
    ROLE_CREATE: "role.create",
    ROLE_UPDATE: "role.update",
    ROLE_DELETE: "role.delete",

    // Employee
    EMPLOYEE_LIST: "employee.list",
    EMPLOYEE_VIEW: "employee.view",
    EMPLOYEE_CREATE: "employee.create",
    EMPLOYEE_UPDATE: "employee.update",
    EMPLOYEE_DELETE: "employee.delete",
    EMPLOYEE_PERSONAL_INFO_VIEW: "employee.personal_info.view",
    EMPLOYEE_EMPLOYMENT_INFO_VIEW: "employee.employment_info.view",

    // Employee Documents
    EMPLOYEE_DOCUMENTS_VIEW: "employee.documents.view",
    EMPLOYEE_DOCUMENTS_UPLOAD: "employee.documents.upload",
    EMPLOYEE_DOCUMENTS_REPLACE: "employee.documents.replace",
    EMPLOYEE_DOCUMENTS_DOWNLOAD: "employee.documents.download",
    EMPLOYEE_DOCUMENTS_DELETE: "employee.documents.delete",
    EMPLOYEE_DOCUMENTS_RESTORE: "employee.documents.restore",
    EMPLOYEE_DOCUMENTS_FORCE_DELETE: "employee.documents.force-delete",
    EMPLOYEE_DOCUMENTS_LIBRARY_SELECT: "employee.documents.library-select",
    EMPLOYEE_DOCUMENTS_HISTORY_VIEW: "employee.documents.history-view",
    EMPLOYEE_DOCUMENTS_HISTORY_DOWNLOAD: "employee.documents.history-download",

    // CV Documents
    CV_DOCUMENTS_VIEW: "cv.documents.view",
    CV_DOCUMENTS_UPLOAD: "cv.documents.upload",
    CV_DOCUMENTS_DOWNLOAD: "cv.documents.download",
    CV_DOCUMENTS_DELETE: "cv.documents.delete",
    CV_DOCUMENTS_HISTORY_VIEW: "cv.documents.history-view",
    CV_DOCUMENTS_HISTORY_DOWNLOAD: "cv.documents.history-download",

    // Questionnaire Documents
    QUESTIONNAIRE_DOCUMENTS_VIEW: "questionnaire.documents.view",
    QUESTIONNAIRE_DOCUMENTS_UPLOAD: "questionnaire.documents.upload",
    QUESTIONNAIRE_DOCUMENTS_DOWNLOAD: "questionnaire.documents.download",
    QUESTIONNAIRE_DOCUMENTS_DELETE: "questionnaire.documents.delete",
    QUESTIONNAIRE_DOCUMENTS_HISTORY_VIEW: "questionnaire.documents.history-view",
    QUESTIONNAIRE_DOCUMENTS_HISTORY_DOWNLOAD: "questionnaire.documents.history-download",

    // Questionnaire
    QUESTIONNAIRE_VIEW: "questionnaire.view",
    QUESTIONNAIRE_REVIEW: "questionnaire.review",
    QUESTIONNAIRE_REJECT: "questionnaire.reject",

    // Document Category
    DOCUMENT_CATEGORY_VIEW: "document-category.view",
    DOCUMENT_CATEGORY_MANAGE: "document-category.manage",

    // Bulk Import
    BULK_IMPORT_EMPLOYEE: "bulk-import.employee",

    // CV
    CV_VIEW: "cv.view",
    CV_APPROVE: "cv.approve",
    CV_REJECT: "cv.reject",
    CV_CREATE_QUESTIONNAIRE: "cv.create-questionnaire",

    // Branding
    BRANDING_VIEW: "branding.view",
    BRANDING_MANAGE: "branding.manage",

    // Form Options
    FORM_OPTIONS_VIEW: "form-options.view",
    FORM_OPTIONS_MANAGE: "form-options.manage",
} as const;

export type PermissionValue = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
