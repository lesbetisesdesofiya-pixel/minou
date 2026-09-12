<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $fillable = ['nom', 'type', 'prix'];

    protected $casts = [
        'prix' => 'float',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_options');
    }

    public function dishes()
    {
        return $this->belongsToMany(Dish::class, 'dish_options');
    }
}
