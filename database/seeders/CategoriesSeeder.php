<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoriesSeeder extends Seeder
{
    /**
     * يضيف فئات دكان البلد الجديدة. آمن تشغيله أكتر من مرة:
     * لو الفئة موجودة (بنفس الـ slug) بيحدّثها بدل ما يكررها.
     * فئة "قطع غيار وزيوت" الموجودة أصلاً مش بتتلمس.
     */
    public function run(): void
    {
        $cats = [
            ['slug' => 'cars-motorcycles', 'name_ar' => 'سيارات وموتوسيكلات', 'name_en' => 'Cars & Motorcycles', 'icon' => '🚗'],
            ['slug' => 'home-appliances',  'name_ar' => 'أجهزة كهربائية ومنزلية', 'name_en' => 'Home Appliances', 'icon' => '🔌'],
            ['slug' => 'real-estate',      'name_ar' => 'عقارات (بيع وإيجار)', 'name_en' => 'Real Estate', 'icon' => '🏢'],
            ['slug' => 'mobiles-tablets',  'name_ar' => 'موبايلات وتابلت', 'name_en' => 'Mobiles & Tablets', 'icon' => '📱'],
            ['slug' => 'furniture',        'name_ar' => 'أثاث ومفروشات', 'name_en' => 'Furniture', 'icon' => '🛋️'],
            ['slug' => 'clothes-shoes',    'name_ar' => 'ملابس وأحذية', 'name_en' => 'Clothes & Shoes', 'icon' => '👕'],
            ['slug' => 'pets',             'name_ar' => 'حيوانات أليفة', 'name_en' => 'Pets', 'icon' => '🐾'],
            ['slug' => 'jobs-services',    'name_ar' => 'وظائف وخدمات', 'name_en' => 'Jobs & Services', 'icon' => '💼'],
            ['slug' => 'other',            'name_ar' => 'أخرى', 'name_en' => 'Other', 'icon' => '📦'],
        ];

        $columns = Schema::getColumnListing('categories');
        $now = now();
        $added = 0;

        foreach ($cats as $i => $c) {
            $data = [
                'name_ar' => $c['name_ar'],
                'icon' => $c['icon'],
            ];

            // أعمدة اختيارية: بنملاها بس لو موجودة في الجدول
            if (in_array('name_en', $columns)) $data['name_en'] = $c['name_en'];
            if (in_array('name', $columns)) $data['name'] = $c['name_ar'];
            if (in_array('sort_order', $columns)) $data['sort_order'] = $i + 2;
            if (in_array('is_active', $columns)) $data['is_active'] = true;
            if (in_array('updated_at', $columns)) $data['updated_at'] = $now;

            $exists = DB::table('categories')->where('slug', $c['slug'])->exists();
            if (! $exists && in_array('created_at', $columns)) $data['created_at'] = $now;

            DB::table('categories')->updateOrInsert(['slug' => $c['slug']], $data);
            if (! $exists) $added++;
        }

        $this->command?->info("تمت إضافة {$added} فئة جديدة.");
    }
}
