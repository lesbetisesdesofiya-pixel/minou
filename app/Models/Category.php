<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['nom'];

    public function dishes()
    {
        return $this->hasMany(Dish::class);
    }

    public function options()
    {
        return $this->belongsToMany(Option::class, 'category_options');
    }
}
