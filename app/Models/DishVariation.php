<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DishVariation extends Model
{
    protected $fillable = ['dish_id', 'name', 'price', 'group_name', 'stock_kg'];

    protected $casts = [
        'price' => 'float',
        'stock_kg' => 'float',
    ];

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }
}
