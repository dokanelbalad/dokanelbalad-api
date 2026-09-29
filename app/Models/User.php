<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'governorate',
        'is_verified',
        'is_active',
        'frozen_reason',
        'frozen_until',
        'phone_violation_strikes',
        'phone_freeze_count',
        'commission_freeze_count',
        'permanently_banned',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'frozen_until' => 'datetime',
            'permanently_banned' => 'boolean',
        ];
    }

    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * هل الحساب مجمّد دلوقتي؟ لو تجميد الأرقام انتهت مدته، بيترفع لوحده هنا (lazy) وبيصفّر العداد.
     * تجميد العمولة والتجميد اليدوي من الإدارة مفتوحين لحد ما حد يرفعهم صراحة (unfreezeAccount).
     */
    public function isFrozen(): bool
    {
        if ($this->permanently_banned) {
            return true;
        }

        if (! $this->frozen_reason) {
            return false;
        }

        if ($this->frozen_until && now()->greaterThanOrEqualTo($this->frozen_until)) {
            $this->update([
                'frozen_reason' => null,
                'frozen_until' => null,
                'phone_violation_strikes' => 0,
            ]);

            return false;
        }

        return true;
    }

    public function freezeAccount(string $reason, $until = null): void
    {
        $this->update([
            'frozen_reason' => $reason,
            'frozen_until' => $until,
        ]);
    }

    public function unfreezeAccount(): void
    {
        $this->update([
            'frozen_reason' => null,
            'frozen_until' => null,
        ]);
    }

    /**
     * بيتسجل كل مرة حد يحاول يبعت رقم تليفون في محادثة. بيرجع الإجراء المطلوب:
     * 'warned' (1، 2 - اتبعتت مع تحذير) / 'blocked' (3 - اتمنعت) / 'blocked_and_frozen' (4 - اتمنعت واتجمد الحساب)
     */
    public function registerPhoneViolation(): string
    {
        $this->increment('phone_violation_strikes');
        $this->refresh();
        $strikes = $this->phone_violation_strikes;

        if ($strikes >= 4) {
            $freezeCount = $this->phone_freeze_count + 1;
            $banned = $freezeCount >= 2;

            $this->update([
                'phone_freeze_count' => $freezeCount,
                'permanently_banned' => $banned,
                'frozen_reason' => 'phone_sharing',
                'frozen_until' => $banned ? null : now()->addDay(),
            ]);

            return 'blocked_and_frozen';
        }

        if ($strikes === 3) {
            return 'blocked';
        }

        return 'warned';
    }

    public function frozenMessage(): string
    {
        if ($this->permanently_banned) {
            return 'تم إغلاق حسابك بشكل نهائي لمخالفة سياسة الموقع. للاستفسار تواصل معنا من صفحة اتصل بنا.';
        }

        return match ($this->frozen_reason) {
            'phone_sharing' => 'حسابك مجمّد مؤقتاً لمخالفة سياسة الموقع (مشاركة وسائل تواصل بره الموقع). هيرجع يشتغل تلقائياً بعد انتهاء مدة التجميد.',
            'commission_overdue' => 'حسابك مجمّد لوجود عمولة مستحقة للموقع لم يتم سدادها. هيرجع يشتغل تلقائياً فور سداد المبلغ بالكامل.',
            'admin_manual' => 'تم تجميد حسابك من إدارة الموقع. تواصل معنا من صفحة اتصل بنا لمعرفة التفاصيل.',
            default => 'حسابك مجمّد مؤقتاً.',
        };
    }
}
