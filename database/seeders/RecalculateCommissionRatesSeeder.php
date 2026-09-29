<?php

namespace Database\Seeders;

use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

/**
 * تشغيل لمرة واحدة (اختياري): بيطبّق نظام العمولة الجديد (2% / أول 100 بائع 1%)
 * على البائعين المعتمدين أصلاً قبل التحديث. شغّله بس لو عندك بائعين معتمدين
 * فعلاً على الموقع اللايف وعايز عمولتهم تتحدث لنفس النظام الجديد.
 *
 * الترتيب بيعتمد على تاريخ التسجيل (created_at) كتقريب لترتيب الموافقة، لأننا
 * معندناش عمود منفصل لتاريخ الموافقة.
 */
class RecalculateCommissionRatesSeeder extends Seeder
{
    public function run(): void
    {
        $approved = VendorProfile::where('status', 'approved')
            ->orderBy('created_at')
            ->get();

        $founding = 0;

        foreach ($approved as $vendor) {
            $isFounding = $founding < 100;
            $vendor->update([
                'is_founding_seller' => $isFounding,
                'commission_rate' => $isFounding ? 1.00 : 2.00,
            ]);
            if ($isFounding) {
                $founding++;
            }
        }

        $this->command?->info("تم تحديث عمولة {$approved->count()} بائع معتمد، منهم {$founding} بائع مؤسس.");
    }
}
