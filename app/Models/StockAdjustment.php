<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    public const REASONS = [
        'opening_stock' => 'Opening stock',
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'count_correction' => 'Count correction',
        'other' => 'Other',
    ];

    protected $fillable = ['adj_no', 'adj_date', 'reason', 'notes', 'created_by'];

    protected $casts = ['adj_date' => 'date'];

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
