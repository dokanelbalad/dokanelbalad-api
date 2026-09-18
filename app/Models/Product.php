<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'title',
        'description',
        'condition',
        'price',
        'discount_percentage',
        'shipping_fee',
        'shipping_paid_by',
        'quantity',
        'governorate',
        'status',
        'views_count',
        'is_featured',
        'featured_until',
    ];

    protected $appends = ['price_after_discount', 'display_price', 'display_shipping_fee', 'total_display_price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'is_featured' => 'boolean',
            'featured_until' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getPriceAfterDiscountAttribute()
    {
        if ($this->discount_percentage > 0) {
            return round($this->price * (1 - $this->discount_percentage / 100), 2);
        }
        return (float) $this->price;
    }

    public function getDisplayPriceAttribute()
    {
        $rate = (float) \App\Models\PlatformSetting::get('buyer_commission_rate', 0);
        return round($this->price_after_discount * (1 + $rate / 100), 2);
    }

    public function getDisplayShippingFeeAttribute()
    {
        return $this->shipping_paid_by === 'buyer' ? (float) $this->shipping_fee : 0;
    }

    public function getTotalDisplayPriceAttribute()
    {
        return $this->display_price + $this->display_shipping_fee;
    }
}