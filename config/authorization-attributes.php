<?php

use App\Domains\Cv\Models\Cv;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Employee\Models\Employee;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Resource Types
    |--------------------------------------------------------------------------
    |
    | Map Eloquent models to the resource type prefix used in policy attribute
    | keys (e.g. `employee.employment_status`, `document.category.slug`). The Authorization
    | engine never inspects models directly — it resolves attributes through the
    | AttributeRegistry using these mappings.
    |
    */

    'models' => [
        User::class => 'user',
        Employee::class => 'employee',
        Document::class => 'document',
        DocumentUsage::class => 'document_usage',
        Questionnaire::class => 'questionnaire',
        Cv::class => 'cv',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute Definitions
    |--------------------------------------------------------------------------
    |
    | Attributes are keyed by their full policy key (<resource_type>.<path>).
    | The path may traverse Eloquent relationships (e.g. `document.category.slug`).
    |
    |   label      – human readable name (for the future Policy Builder UI)
    |   type       – integer | string | boolean | date (used for operator validation)
    |   queryable  – whether the attribute can be translated to a WHERE clause
    |   column     – the DB column holding the value. For related attributes the
    |                column lives on the deepest related model, reached through
    |                the `relations` chain via whereHas.
    |
    | Only attributes that exist in the current schema are registered.
    | Department and site-of-work attributes will be added when the data
    | model supports them (site will land as an employee field).
    |
    */

    'attributes' => [

        'user' => [
            'user.id' => ['label' => 'شناسه کاربر', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'user.name' => ['label' => 'نام کاربر', 'type' => 'string', 'queryable' => true, 'column' => 'name'],
            'user.email' => ['label' => 'ایمیل', 'type' => 'string', 'queryable' => true, 'column' => 'email'],
            'user.username' => ['label' => 'نام کاربری', 'type' => 'string', 'queryable' => true, 'column' => 'username'],
            'user.phone' => ['label' => 'تلفن', 'type' => 'string', 'queryable' => true, 'column' => 'phone'],
            'user.is_active' => ['label' => 'وضعیت فعال', 'type' => 'boolean', 'queryable' => true, 'column' => 'is_active'],
            'user.employee.id' => ['label' => 'شناسه پرونده کاربر', 'type' => 'integer', 'queryable' => false],
            'user.employee.personnel_code' => ['label' => 'کد پرسنلی کاربر', 'type' => 'string', 'queryable' => false],
            'user.employee.employment_status' => ['label' => 'وضعیت استخدامی کاربر', 'type' => 'string', 'queryable' => false],
            'user.employee.employment_type' => ['label' => 'نوع استخدام کاربر', 'type' => 'string', 'queryable' => false],
        ],

        'employee' => [
            'employee.id' => ['label' => 'شناسه کارمند', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'employee.user_id' => ['label' => 'شناسه کاربر', 'type' => 'integer', 'queryable' => true, 'column' => 'user_id'],
            'employee.personnel_code' => ['label' => 'کد پرسنلی', 'type' => 'string', 'queryable' => true, 'column' => 'personnel_code'],
            'employee.first_name' => ['label' => 'نام', 'type' => 'string', 'queryable' => true, 'column' => 'first_name'],
            'employee.last_name' => ['label' => 'نام خانوادگی', 'type' => 'string', 'queryable' => true, 'column' => 'last_name'],
            'employee.gender' => ['label' => 'جنسیت', 'type' => 'string', 'queryable' => true, 'column' => 'gender'],
            'employee.email' => ['label' => 'ایمیل', 'type' => 'string', 'queryable' => true, 'column' => 'email'],
            'employee.mobile' => ['label' => 'موبایل', 'type' => 'string', 'queryable' => true, 'column' => 'mobile'],
            'employee.id_number' => ['label' => 'کد ملی', 'type' => 'string', 'queryable' => true, 'column' => 'id_number'],
            'employee.employment_status' => ['label' => 'وضعیت استخدامی', 'type' => 'string', 'queryable' => true, 'column' => 'employment_status'],
            'employee.employment_type' => ['label' => 'نوع استخدام', 'type' => 'string', 'queryable' => true, 'column' => 'employment_type'],
            'employee.hire_date' => ['label' => 'تاریخ استخدام', 'type' => 'date', 'queryable' => true, 'column' => 'hire_date'],
        ],

        'document' => [
            'document.id' => ['label' => 'شناسه سند', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'document.category_id' => ['label' => 'شناسه دسته‌بندی', 'type' => 'integer', 'queryable' => true, 'column' => 'category_id'],
            'document.original_name' => ['label' => 'نام فایل', 'type' => 'string', 'queryable' => true, 'column' => 'original_name'],
            'document.mime_type' => ['label' => 'نوع فایل', 'type' => 'string', 'queryable' => true, 'column' => 'mime_type'],
            'document.category.slug' => ['label' => 'اسلاگ دسته‌بندی', 'type' => 'string', 'queryable' => true, 'relations' => ['category'], 'column' => 'slug'],
        ],

        'document_usage' => [
            'document_usage.id' => ['label' => 'شناسه استفاده', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'document_usage.document_id' => ['label' => 'شناسه سند', 'type' => 'integer', 'queryable' => true, 'column' => 'document_id'],
            'document_usage.entity_type' => ['label' => 'نوع موجودیت', 'type' => 'string', 'queryable' => true, 'column' => 'entity_type'],
            'document_usage.entity_id' => ['label' => 'شناسه موجودیت', 'type' => 'integer', 'queryable' => true, 'column' => 'entity_id'],
            'document_usage.section_key' => ['label' => 'کلید بخش', 'type' => 'string', 'queryable' => true, 'column' => 'section_key'],
            'document_usage.field_key' => ['label' => 'کلید فیلد', 'type' => 'string', 'queryable' => true, 'column' => 'field_key'],
            'document_usage.deleted_at' => ['label' => 'در سطل زباله', 'type' => 'date', 'queryable' => true, 'column' => 'deleted_at'],
            'document_usage.document.category_id' => ['label' => 'دسته‌بندی سند', 'type' => 'integer', 'queryable' => true, 'relations' => ['document'], 'column' => 'category_id'],
            'document_usage.document.category.slug' => ['label' => 'اسلاگ دسته‌بندی سند', 'type' => 'string', 'queryable' => true, 'relations' => ['document', 'category'], 'column' => 'slug'],
        ],

        'questionnaire' => [
            'questionnaire.id' => ['label' => 'شناسه پرسشنامه', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'questionnaire.uuid' => ['label' => 'شناسه یکتا', 'type' => 'string', 'queryable' => true, 'column' => 'uuid'],
            'questionnaire.status' => ['label' => 'وضعیت', 'type' => 'string', 'queryable' => true, 'column' => 'status'],
            'questionnaire.first_name' => ['label' => 'نام', 'type' => 'string', 'queryable' => true, 'column' => 'first_name'],
            'questionnaire.last_name' => ['label' => 'نام خانوادگی', 'type' => 'string', 'queryable' => true, 'column' => 'last_name'],
            'questionnaire.email' => ['label' => 'ایمیل', 'type' => 'string', 'queryable' => true, 'column' => 'email'],
            'questionnaire.mobile' => ['label' => 'موبایل', 'type' => 'string', 'queryable' => true, 'column' => 'mobile'],
            'questionnaire.created_at' => ['label' => 'تاریخ ایجاد', 'type' => 'date', 'queryable' => true, 'column' => 'created_at'],
            'questionnaire.reviewed_by' => ['label' => 'بازبین', 'type' => 'integer', 'queryable' => true, 'column' => 'reviewed_by'],
        ],

        'cv' => [
            'cv.id' => ['label' => 'شناسه رزومه', 'type' => 'integer', 'queryable' => true, 'column' => 'id'],
            'cv.uuid' => ['label' => 'شناسه یکتا', 'type' => 'string', 'queryable' => true, 'column' => 'uuid'],
            'cv.status' => ['label' => 'وضعیت', 'type' => 'string', 'queryable' => true, 'column' => 'status'],
            'cv.first_name' => ['label' => 'نام', 'type' => 'string', 'queryable' => true, 'column' => 'first_name'],
            'cv.last_name' => ['label' => 'نام خانوادگی', 'type' => 'string', 'queryable' => true, 'column' => 'last_name'],
            'cv.email' => ['label' => 'ایمیل', 'type' => 'string', 'queryable' => true, 'column' => 'email'],
            'cv.mobile' => ['label' => 'موبایل', 'type' => 'string', 'queryable' => true, 'column' => 'mobile'],
            'cv.created_at' => ['label' => 'تاریخ ایجاد', 'type' => 'date', 'queryable' => true, 'column' => 'created_at'],
            'cv.reviewed_by' => ['label' => 'بازبین', 'type' => 'integer', 'queryable' => true, 'column' => 'reviewed_by'],
        ],

    ],

];
