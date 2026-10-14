<?php

namespace App\Console\Commands;

use App\Services\Inventory\StockService;
use Illuminate\Console\Command;

class InventoryVerify extends Command
{
    protected $signature = 'inventory:verify';

    protected $description = 'Check that cached stock, batches and the movement ledger agree for every inventory item';

    public function handle(StockService $stock)
    {
        $problems = $stock->verify();
        $stamp = '[' . now()->toDateTimeString() . '] ';

        if (empty($problems)) {
            $this->info($stamp . 'Inventory OK: 0 mismatches.');
            return 0;
        }

        $this->line($stamp . 'inventory:verify found problems:');

        foreach ($problems as $problem) {
            $this->error($problem);
        }
        $this->warn(count($problems) . ' mismatch(es) found.');

        return 1;
    }
}
