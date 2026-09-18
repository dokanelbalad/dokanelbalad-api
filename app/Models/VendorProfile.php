<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vendor_type',
        'entity_type',
        'verification_tier',
        'national_id_number',
        'national_id_front_image',
        'national_id_back_image',
        'is_vat_registered',
        'tax_registration_number',
        'store_name',
        'store_description',
        'store_logo',
        'commercial_register_no',
        'commercial_register_image',
        'address_line1',
        'address_line2',
        'city',
        'is_founding_seller',
        'commission_rate',
        'rating_avg',
        'rating_count',
        'status',
        'pending_commission_balance',
    ];

    protected $appends = ['national_id_front_url', 'national_id_back_url', 'commercial_register_url'];

    protected function casts(): array
    {
        return [
            'is_founding_seller' => 'boolean',
            'is_vat_registered' => 'boolean',
            'commission_rate' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'pending_commission_balance' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getOrderLimitAttribute()
    {
        return $this->verification_tier === 'verified' ? null : 25000;
    }

    public function getNationalIdFrontUrlAttribute()
    {
        return $this->national_id_front_image ? asset('storage/' . $this->national_id_front_image) : null;
    }

    public function getNationalIdBackUrlAttribute()
    {
        return $this->national_id_back_image ? asset('storage/' . $this->national_id_back_image) : null;
    }

    public function getCommercialRegisterUrlAttribute()
    {
        return $this->commercial_register_image ? asset('storage/' . $this->commercial_register_image) : null;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'vendor_id');
    }

    public function commissionTransactions()
    {
        return $this->hasMany(CommissionTransaction::class, 'vendor_id');
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'vendor_id');
    }
}