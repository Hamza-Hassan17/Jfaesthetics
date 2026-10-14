<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}
