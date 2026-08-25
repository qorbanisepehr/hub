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
                'name' => 'مشخصات فردی',
                'slug' => 'personal-info',
                'sort_order' => 1,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'شناسنامه', 'slug' => 'birth-certificate', 'sort_order' => 1],
                    ['name' => 'کارت ملی', 'slug' => 'national-card', 'sort_order' => 2],
                    ['name' => 'عکس پرسنلی', 'slug' => 'personnel-photo', 'sort_order' => 3],
                    ['name' => 'نمونه امضا', 'slug' => 'signature-sample', 'sort_order' => 4],
                    ['name' => 'گواهی عدم سوء پیشینه', 'slug' => 'criminal-record-certificate', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'احکام',
                'slug' => 'decrees',
                'sort_order' => 2,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'حکم کارگزینی', 'slug' => 'appointment-decree', 'sort_order' => 1],
                    ['name' => 'حکم انتصاب', 'slug' => 'appointment-order', 'sort_order' => 2],
                    ['name' => 'حکم ترفیع', 'slug' => 'promotion-decree', 'sort_order' => 3],
                    ['name' => 'حکم تبدیل وضعیت', 'slug' => 'status-change-decree', 'sort_order' => 4],
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
                'name' => 'نامه‌های اداری',
                'slug' => 'official-letters',
                'sort_order' => 5,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'نامه مرخصی', 'slug' => 'leave-letter', 'sort_order' => 1],
                    ['name' => 'نامه استعلام', 'slug' => 'inquiry-letter', 'sort_order' => 2],
                    ['name' => 'نامه ماموریت', 'slug' => 'mission-letter', 'sort_order' => 3],
                    ['name' => 'نامه انتقال', 'slug' => 'transfer-letter', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'نامه‌های مالی',
                'slug' => 'financial-letters',
                'sort_order' => 6,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'فیش حقوقی', 'slug' => 'payslip', 'sort_order' => 1],
                    ['name' => 'گواهی کسر از حقوق', 'slug' => 'salary-deduction-letter', 'sort_order' => 2],
                    ['name' => 'حکم حقوقی', 'slug' => 'salary-decree', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'تامین اجتماعی',
                'slug' => 'social-security',
                'sort_order' => 7,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'سابقه بیمه', 'slug' => 'insurance-history', 'sort_order' => 1],
                    ['name' => 'برگ بیمه', 'slug' => 'insurance-form', 'sort_order' => 2],
                    ['name' => 'لیست بیمه', 'slug' => 'insurance-list', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'طب کار',
                'slug' => 'occupational-medicine',
                'sort_order' => 8,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'گواهی سلامت', 'slug' => 'health-certificate', 'sort_order' => 1],
                    ['name' => 'آزمایشات پزشکی', 'slug' => 'medical-tests', 'sort_order' => 2],
                    ['name' => 'ارزیابی پزشکی', 'slug' => 'medical-evaluation', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'طبقه‌بندی مشاغل',
                'slug' => 'job-classification',
                'sort_order' => 9,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'ارزیابی شغلی', 'slug' => 'job-evaluation', 'sort_order' => 1],
                    ['name' => 'چارت سازمانی', 'slug' => 'org-chart', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'رزومه',
                'slug' => 'cv',
                'sort_order' => 10,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'رزومه', 'slug' => 'resume', 'sort_order' => 1],
                    ['name' => 'نامه معرفی', 'slug' => 'cover-letter', 'sort_order' => 2],
                ],
            ],
            [
                'name' => 'سایر مدارک',
                'slug' => 'other',
                'sort_order' => 11,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'سایر مدارک', 'slug' => 'other-documents', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'استعلام‌ها',
                'slug' => 'inquiries',
                'sort_order' => 12,
                'type' => DocumentCategory::TYPE_PERSONNEL,
                'children' => [
                    ['name' => 'نتیجه استعلام', 'slug' => 'inquiry-result', 'sort_order' => 1],
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
    }
}
