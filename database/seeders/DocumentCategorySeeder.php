<?php

namespace Database\Seeders;

use App\Domains\Document\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'مدارک هویتی',
                'slug' => 'identity-docs',
                'sort_order' => 1,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'شناسنامه', 'slug' => 'birth-certificate', 'sort_order' => 1],
                    ['name' => 'کارت ملی', 'slug' => 'national-card', 'sort_order' => 2],
                    ['name' => 'عکس پرسنلی', 'slug' => 'personnel-photo', 'sort_order' => 3],
                    ['name' => 'نمونه امضا', 'slug' => 'signature-sample', 'sort_order' => 4],
                    ['name' => 'کارت پایان خدمت', 'slug' => 'military-card', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'مدارک وابستگان',
                'slug' => 'dependents-documents',
                'sort_order' => 2,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'مدارک هویتی وابستگان', 'slug' => 'dependents-identity', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'آموزش',
                'slug' => 'education',
                'sort_order' => 3,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'مدرک تحصیلی', 'slug' => 'academic-degree', 'sort_order' => 1],
                    ['name' => 'گواهینامه دوره‌ها', 'slug' => 'course-certificates', 'sort_order' => 2],
                    ['name' => 'ریز نمرات', 'slug' => 'transcript', 'sort_order' => 3],
                    ['name' => 'گواهی زبان', 'slug' => 'language-certificate', 'sort_order' => 4],
                    ['name' => 'گواهی مهارت', 'slug' => 'skill-certificate', 'sort_order' => 5],
                    ['name' => 'مدارک پژوهشی', 'slug' => 'research-documents', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'سوابق شغلی',
                'slug' => 'work-experience',
                'sort_order' => 4,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'گواهی اشتغال به کار', 'slug' => 'employment-certificate', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'احکام و تغییرات',
                'slug' => 'decrees',
                'sort_order' => 5,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'حکم کارگزینی', 'slug' => 'appointment-decree', 'sort_order' => 1],
                    ['name' => 'حکم انتصاب', 'slug' => 'appointment-order', 'sort_order' => 2],
                    ['name' => 'حکم ترفیع', 'slug' => 'promotion-decree', 'sort_order' => 3],
                    ['name' => 'حکم تبدیل وضعیت', 'slug' => 'status-change-decree', 'sort_order' => 4],
                    ['name' => 'نامه تغییر پست', 'slug' => 'position-change-letter', 'sort_order' => 5],
                    ['name' => 'قرارداد', 'slug' => 'contract', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'استخدام و مصاحبه',
                'slug' => 'hiring',
                'sort_order' => 6,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'پرسشنامه استخدامی', 'slug' => 'recruitment-questionnaire', 'sort_order' => 1],
                    ['name' => 'مصاحبه منابع انسانی', 'slug' => 'hr-interview', 'sort_order' => 2],
                    ['name' => 'مصاحبه تخصصی', 'slug' => 'technical-interview', 'sort_order' => 3],
                    ['name' => 'مصاحبه', 'slug' => 'interview', 'sort_order' => 4],
                    ['name' => 'نامه شروع به کار', 'slug' => 'start-work-letter', 'sort_order' => 5],
                    ['name' => 'اعلام نیاز', 'slug' => 'job-requisition', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'مالی',
                'slug' => 'financial',
                'sort_order' => 7,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'فیش حقوقی', 'slug' => 'payslip', 'sort_order' => 1],
                    ['name' => 'گواهی کسر از حقوق', 'slug' => 'salary-deduction-letter', 'sort_order' => 2],
                    ['name' => 'حکم حقوقی', 'slug' => 'salary-decree', 'sort_order' => 3],
                    ['name' => 'تعیین حقوق اولیه', 'slug' => 'initial-salary', 'sort_order' => 4],
                    ['name' => 'تغییر حقوق', 'slug' => 'salary-change', 'sort_order' => 5],
                    ['name' => 'اقرارنامه مالی', 'slug' => 'financial-affidavit', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'بیمه تکمیلی',
                'slug' => 'supplementary-insurance',
                'sort_order' => 8,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'فرم بیمه تکمیلی', 'slug' => 'supplementary-insurance-form', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'تامین اجتماعی',
                'slug' => 'social-security',
                'sort_order' => 9,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'سابقه بیمه', 'slug' => 'insurance-history', 'sort_order' => 1],
                    ['name' => 'برگ بیمه', 'slug' => 'insurance-form', 'sort_order' => 2],
                    ['name' => 'لیست بیمه', 'slug' => 'insurance-list', 'sort_order' => 3],
                    ['name' => 'سابقه بیمه با ریال', 'slug' => 'insurance-history-rial', 'sort_order' => 4],
                    ['name' => 'گزارش سوابق و ریزدستمزد', 'slug' => 'insurance-history-summary', 'sort_order' => 5],
                    ['name' => 'گزارش سوابق تلفیقی', 'slug' => 'insurance-history-rial-summary', 'sort_order' => 6],
                    ['name' => 'گزارش سوابق کلی', 'slug' => 'insurance-history-overall', 'sort_order' => 7],
                    ['name' => 'آخرین عناوین شغلی', 'slug' => 'insurance-last-job-titles', 'sort_order' => 8],
                ],
            ],
            [
                'name' => 'طب کار',
                'slug' => 'occupational-medicine',
                'sort_order' => 10,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'گواهی سلامت', 'slug' => 'health-certificate', 'sort_order' => 1],
                    ['name' => 'آزمایشات پزشکی', 'slug' => 'medical-tests', 'sort_order' => 2],
                    ['name' => 'ارزیابی پزشکی', 'slug' => 'medical-evaluation', 'sort_order' => 3],
                    ['name' => 'فرم تعهد انجام طب کار', 'slug' => 'occupational-medicine-commitment', 'sort_order' => 4],
                    ['name' => 'فرم بدو استخدام', 'slug' => 'pre-employment-form', 'sort_order' => 5],
                    ['name' => 'فرم پاسخ استعلام خوداظهاری', 'slug' => 'self-declaration-response', 'sort_order' => 6],
                    ['name' => 'فرم تطبیق وضعیت متقاضی', 'slug' => 'applicant-status-match', 'sort_order' => 7],
                ],
            ],
            [
                'name' => 'نامه‌های اداری',
                'slug' => 'official-letters',
                'sort_order' => 11,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'نامه مرخصی', 'slug' => 'leave-letter', 'sort_order' => 1],
                    ['name' => 'نامه استعلام', 'slug' => 'inquiry-letter', 'sort_order' => 2],
                    ['name' => 'نامه ماموریت', 'slug' => 'mission-letter', 'sort_order' => 3],
                    ['name' => 'نامه انتقال', 'slug' => 'transfer-letter', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'فرم‌ها',
                'slug' => 'forms',
                'sort_order' => 12,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'فرم ذینفع', 'slug' => 'beneficiary-form', 'sort_order' => 1],
                    ['name' => 'فرم ناهار', 'slug' => 'lunch-form', 'sort_order' => 2],
                    ['name' => 'شکایت اداره کار', 'slug' => 'labor-complaint', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'طبقه‌بندی مشاغل',
                'slug' => 'job-classification',
                'sort_order' => 13,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'ارزیابی شغلی', 'slug' => 'job-evaluation', 'sort_order' => 1],
                    ['name' => 'چارت سازمانی', 'slug' => 'org-chart', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'رزومه',
                'slug' => 'cv',
                'sort_order' => 14,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'رزومه', 'slug' => 'resume', 'sort_order' => 1],
                    ['name' => 'نامه معرفی', 'slug' => 'cover-letter', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'استعلام‌ها',
                'slug' => 'inquiries',
                'sort_order' => 15,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'نتیجه استعلام', 'slug' => 'inquiry-result', 'sort_order' => 1],
                    ['name' => 'گواهی عدم سوء پیشینه', 'slug' => 'criminal-record-certificate', 'sort_order' => 2],
                    ['name' => 'فرم صحت‌سنجی ثنا', 'slug' => 'sana-verification-form', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'سایر مدارک',
                'slug' => 'other',
                'sort_order' => 16,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'سایر مدارک', 'slug' => 'other-documents', 'sort_order' => 1],
                ],
            ],
        ];

        foreach ($categories as $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = DocumentCategory::updateOrCreate(
                ['slug' => $parentData['slug']],
                $parentData,
            );

            foreach ($children as $childData) {
                $childData['parent_id'] = $parent->id;
                $childData['type'] = $parentData['type'];

                DocumentCategory::updateOrCreate(
                    ['slug' => $childData['slug']],
                    $childData,
                );
            }
        }

        // Clean up obsolete parent groups that no longer exist in the tree.
        // Renamed parents (personal-info → identity-docs, financial-letters →
        // financial) re-parent their children via updateOrCreate above; once a
        // parent has no children left it is an empty shell and safe to delete.
        foreach (['personal-info', 'financial-letters'] as $obsoleteSlug) {
            DocumentCategory::query()
                ->where('slug', $obsoleteSlug)
                ->whereDoesntHave('children')
                ->delete();
        }
    }
}
