export const PERMISSIONS = {
    // Employee
    EMPLOYEE_LIST: "employee.list",
    EMPLOYEE_VIEW: "employee.view",
    EMPLOYEE_CREATE: "employee.create",
    EMPLOYEE_UPDATE: "employee.update",
    EMPLOYEE_DELETE: "employee.delete",

    // Employee Documents
    EMPLOYEE_DOCUMENTS_VIEW: "employee.documents.view",
    EMPLOYEE_DOCUMENTS_UPLOAD: "employee.documents.upload",
    EMPLOYEE_DOCUMENTS_DOWNLOAD: "employee.documents.download",
    EMPLOYEE_DOCUMENTS_DELETE: "employee.documents.delete",
    EMPLOYEE_DOCUMENTS_LIBRARY_SELECT: "employee.documents.library-select",

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
