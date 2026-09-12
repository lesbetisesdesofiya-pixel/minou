<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['quartier', 'fee', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
