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
            ['name' => 'مشخصات فردی', 'slug' => 'personal-info', 'sort_order' => 1, 'documentable_type' => Employee::class],
            ['name' => 'احکام', 'slug' => 'decrees', 'sort_order' => 2, 'documentable_type' => Employee::class],
            ['name' => 'آموزش', 'slug' => 'education', 'sort_order' => 3, 'documentable_type' => Employee::class],
            ['name' => 'نامه‌های اداری', 'slug' => 'official-letters', 'sort_order' => 4, 'documentable_type' => Employee::class],
            ['name' => 'نامه‌های مالی', 'slug' => 'financial-letters', 'sort_order' => 5, 'documentable_type' => Employee::class],
            ['name' => 'تامین اجتماعی', 'slug' => 'social-security', 'sort_order' => 6, 'documentable_type' => Employee::class],
            ['name' => 'طب کار', 'slug' => 'occupational-medicine', 'sort_order' => 7, 'documentable_type' => Employee::class],
            ['name' => 'عکس و نمونه امضا', 'slug' => 'photo-signature', 'sort_order' => 8, 'documentable_type' => Employee::class],
            ['name' => 'طبقه‌بندی مشاغل', 'slug' => 'job-classification', 'sort_order' => 9, 'documentable_type' => Employee::class],
        ];

        foreach ($categories as $category) {
            DocumentCategory::create($category);
        }
    }
}
