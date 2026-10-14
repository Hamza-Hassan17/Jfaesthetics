<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a service's recipe. Only read when a service is added to an invoice. */
class ServiceItem extends Model
{
    protected $fillable = ['service_id', 'item_id', 'qty_per_session', 'is_billable', 'created_by'];

    protected $casts = ['qty_per_session' => 'integer', 'is_billable' => 'boolean'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }
}
