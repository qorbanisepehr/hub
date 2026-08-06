export const PERMISSIONS = {
    // Employee
    EMPLOYEE_VIEW_OWN: "employee.view_own",
    EMPLOYEE_VIEW_ALL: "employee.view_all",
    EMPLOYEE_CREATE: "employee.create",
    EMPLOYEE_UPDATE_OWN: "employee.update_own",
    EMPLOYEE_UPDATE_ALL: "employee.update_all",
    EMPLOYEE_DELETE: "employee.delete",

    // Document
    DOCUMENT_VIEW_OWN: "document.view_own",
    DOCUMENT_VIEW_ALL: "document.view_all",
    DOCUMENT_UPLOAD_OWN: "document.upload_own",
    DOCUMENT_UPLOAD_ALL: "document.upload_all",
    DOCUMENT_DOWNLOAD_OWN: "document.download_own",
    DOCUMENT_DOWNLOAD_ALL: "document.download_all",
    DOCUMENT_DELETE_OWN: "document.delete_own",
    DOCUMENT_DELETE_ALL: "document.delete_all",

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
} as const;

export type PermissionValue = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
