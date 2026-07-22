<?php

namespace Database\Seeders;

use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Employee\Models\Employee;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'personal-info' => [
                'name' => 'مشخصات فردی',
                'slug' => 'personal-info',
                'sort_order' => 1,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'شناسنامه', 'slug' => 'birth-certificate', 'sort_order' => 1, 'children' => [
                        ['name' => 'صفحه اول', 'slug' => 'birth-certificate-page1', 'sort_order' => 1],
                        ['name' => 'صفحه دوم', 'slug' => 'birth-certificate-page2', 'sort_order' => 2],
                        ['name' => 'صفحه سوم', 'slug' => 'birth-certificate-page3', 'sort_order' => 3],
                    ]],
                    ['name' => 'کارت ملی', 'slug' => 'national-card', 'sort_order' => 2, 'children' => [
                        ['name' => 'رو', 'slug' => 'national-card-front', 'sort_order' => 1],
                        ['name' => 'پشت', 'slug' => 'national-card-back', 'sort_order' => 2],
                    ]],
                    ['name' => 'عکس متقاضی', 'slug' => 'applicant-photo', 'sort_order' => 3],
                    ['name' => 'نمونه امضا', 'slug' => 'signature-sample', 'sort_order' => 4],
                ],
            ],
            'decrees' => [
                'name' => 'احکام',
                'slug' => 'decrees',
                'sort_order' => 2,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'حکم کارگزینی', 'slug' => 'appointment-decree', 'sort_order' => 1],
                    ['name' => 'حکم انتصاب', 'slug' => 'appointment-order', 'sort_order' => 2],
                    ['name' => 'حکم ترفیع', 'slug' => 'promotion-decree', 'sort_order' => 3],
                    ['name' => 'حکم تبدیل وضعیت', 'slug' => 'status-change-decree', 'sort_order' => 4],
                ],
            ],
            'education' => [
                'name' => 'آموزش',
                'slug' => 'education',
                'sort_order' => 3,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'مدرک تحصیلی', 'slug' => 'academic-degree', 'sort_order' => 1],
                    ['name' => 'گواهینامه دوره‌ها', 'slug' => 'course-certificates', 'sort_order' => 2],
                    ['name' => 'ریز نمرات', 'slug' => 'transcript', 'sort_order' => 3],
                    ['name' => 'گواهی زبان', 'slug' => 'language-certificate', 'sort_order' => 4],
                ],
            ],
            'official-letters' => [
                'name' => 'نامه‌های اداری',
                'slug' => 'official-letters',
                'sort_order' => 4,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'نامه مرخصی', 'slug' => 'leave-letter', 'sort_order' => 1],
                    ['name' => 'نامه استعلام', 'slug' => 'inquiry-letter', 'sort_order' => 2],
                    ['name' => 'نامه ماموریت', 'slug' => 'mission-letter', 'sort_order' => 3],
                    ['name' => 'نامه انتقال', 'slug' => 'transfer-letter', 'sort_order' => 4],
                ],
            ],
            'financial-letters' => [
                'name' => 'نامه‌های مالی',
                'slug' => 'financial-letters',
                'sort_order' => 5,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'فیش حقوقی', 'slug' => 'payslip', 'sort_order' => 1],
                    ['name' => 'گواهی کسر از حقوق', 'slug' => 'salary-deduction-letter', 'sort_order' => 2],
                    ['name' => 'حکم حقوقی', 'slug' => 'salary-decree', 'sort_order' => 3],
                ],
            ],
            'social-security' => [
                'name' => 'تامین اجتماعی',
                'slug' => 'social-security',
                'sort_order' => 6,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'سابقه بیمه', 'slug' => 'insurance-history', 'sort_order' => 1],
                    ['name' => 'برگ بیمه', 'slug' => 'insurance-form', 'sort_order' => 2],
                    ['name' => 'لیست بیمه', 'slug' => 'insurance-list', 'sort_order' => 3],
                ],
            ],
            'occupational-medicine' => [
                'name' => 'طب کار',
                'slug' => 'occupational-medicine',
                'sort_order' => 7,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'گواهی سلامت', 'slug' => 'health-certificate', 'sort_order' => 1],
                    ['name' => 'آزمایشات پزشکی', 'slug' => 'medical-tests', 'sort_order' => 2],
                    ['name' => 'ارزیابی پزشکی', 'slug' => 'medical-evaluation', 'sort_order' => 3],
                ],
            ],
            'photo-signature' => [
                'name' => 'عکس و نمونه امضا',
                'slug' => 'photo-signature',
                'sort_order' => 8,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'عکس پرسنلی', 'slug' => 'personnel-photo', 'sort_order' => 1],
                    ['name' => 'نمونه امضا', 'slug' => 'signature', 'sort_order' => 2],
                ],
            ],
            'job-classification' => [
                'name' => 'طبقه‌بندی مشاغل',
                'slug' => 'job-classification',
                'sort_order' => 9,
                'documentable_type' => Employee::class,
                'children' => [
                    ['name' => 'ارزیابی شغلی', 'slug' => 'job-evaluation', 'sort_order' => 1],
                    ['name' => 'چارت سازمانی', 'slug' => 'org-chart', 'sort_order' => 2],
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
                $grandChildren = $childData['children'] ?? [];
                unset($childData['children']);

                $childData['documentable_type'] = $parent->documentable_type;
                $childData['parent_id'] = $parent->id;

                $child = DocumentCategory::updateOrCreate(
                    ['slug' => $childData['slug']],
                    $childData,
                );

                foreach ($grandChildren as $grandChildData) {
                    $grandChildData['documentable_type'] = $parent->documentable_type;
                    $grandChildData['parent_id'] = $child->id;

                    DocumentCategory::updateOrCreate(
                        ['slug' => $grandChildData['slug']],
                        $grandChildData,
                    );
                }
            }
        }
    }
}
