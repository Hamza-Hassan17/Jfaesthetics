<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentSequence extends Model
{
    protected $fillable = ['doc_type', 'year', 'last_number', 'created_by'];

    /**
     * Next number for a document type, e.g. next('ADJ') => "ADJ-2600001".
     * Two-digit year + 5-digit counter, matching the invoice number style;
     * the counter restarts each year.
     */
    public static function next(string $docType): string
    {
        return DB::transaction(function () use ($docType) {
            $year = (int) now()->format('y');

            $sequence = static::where('doc_type', $docType)->where('year', $year)->lockForUpdate()->first()
                ?? static::create(['doc_type' => $docType, 'year' => $year, 'last_number' => 0, 'created_by' => auth()->id()]);

            $sequence->increment('last_number');

            return $docType . '-' . str_pad($year, 2, '0', STR_PAD_LEFT) . str_pad($sequence->last_number, 5, '0', STR_PAD_LEFT);
        });
    }
}
