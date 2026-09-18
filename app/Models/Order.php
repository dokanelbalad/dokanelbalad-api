<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'vendor_id',
        'status',
        'payment_method',
        'subtotal',
        'commission_rate_applied',
        'commission_amount',
        'total',
        'shipping_address',
        'shipping_governorate',
        'confirmed_by_buyer_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'commission_rate_applied' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_by_buyer_at' => 'datetime',
        ];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function commissionTransactions()
    {
        return $this->hasMany(CommissionTransaction::class);
    }
}