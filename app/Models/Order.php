<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'service_type', 'client_name', 'client_phone', 'neighborhood',
        'table_number', 'notes', 'total_amount', 'status', 'assigned_driver_id',
        'payment_method', 'transaction_reference', 'screenshot_path',
        'payment_token', 'payment_url', 'moneyfusion_status',
        'subtotal_amount', 'service_fee', 'delivery_fee'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryPerson::class, 'assigned_driver_id');
    }
}
