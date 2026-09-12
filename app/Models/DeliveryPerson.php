<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPerson extends Model
{
    protected $table = 'delivery_persons';

    protected $fillable = ['last_name', 'first_name', 'phone', 'email', 'active', 'suspended'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'assigned_driver_id');
    }
}
