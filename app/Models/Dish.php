<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'nom', 'description', 'prix', 'category_id',
        'image', 'menu_type', 'active', 'orders_count'
    ];

    protected $casts = [
        'prix' => 'float',
        'active' => 'boolean',
        'orders_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variations()
    {
        return $this->hasMany(DishVariation::class);
    }

    public function options()
    {
        return $this->belongsToMany(Option::class, 'dish_options');
    }
}
