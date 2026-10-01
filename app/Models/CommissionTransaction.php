<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'conversation_id',
        'amount',
        'type',
        'status',
        'settled_at',
        'buyer_confirmation',
        'confirmation_deadline',
        'buyer_response_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'settled_at' => 'datetime',
            'confirmation_deadline' => 'datetime',
            'buyer_response_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
