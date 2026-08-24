<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class medicine extends Model
{
    use HasFactory,softDeletes;
    protected $fillable=[
        'name',
        'price',
        'quantity',
        'code',
        'low_stock_threshold',
    ];

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getIsLowStockAttribute()
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}
