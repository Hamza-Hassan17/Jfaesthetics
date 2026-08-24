<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'change',
        'reason',
        'reference_type',
        'reference_id',
        'cost',
        'user_id',
        'notes',
    ];

    public function medicine()
    {
        return $this->belongsTo(medicine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
