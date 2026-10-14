<?php

namespace Tests\Feature\Inventory;

use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemBatch;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->stock = app(StockService::class);
        $this->item = InventoryItem::create(['code' => 'SYR-3', 'name' => 'Syringe 3ml', 'cost_price' => 40, 'sale_price' => 50]);
    }

    private function receive(int $qty, ?string $expiry, ?string $batchNo = null, float $cost = 40): ItemBatch
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);

        return $this->stock->stockIn($this->item, $qty, $batchNo, $expiry, $cost, $adjustment, null, 'adjustment_in');
    }

    private function sale(): Invoice
    {
        $patient = patient::create(['name' => 'Ramsha', 'phone' => '1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+', 'email' => uniqid() . '@x.com']);

        return Invoice::create(['invoice_number' => uniqid(), 'patient_id' => $patient->id]);
    }

    private function onHand(): int
    {
        return $this->item->fresh()->qty_on_hand;
    }

    public function test_a_sale_takes_from_the_earliest_expiring_batch_first()
    {
        $later = $this->receive(5, now()->addDays(90)->toDateString(), 'A');
        $sooner = $this->receive(5, now()->addDays(30)->toDateString(), 'B');

        $this->stock->stockOut($this->item, 3, $this->sale());

        $this->assertEquals(2, $sooner->fresh()->qty_remaining);
        $this->assertEquals(5, $later->fresh()->qty_remaining);
        $this->assertEquals(7, $this->onHand());
    }

    public function test_a_sale_spanning_batches_writes_one_movement_per_batch()
    {
        $this->receive(2, now()->addDays(30)->toDateString(), 'B');
        $this->receive(5, now()->addDays(90)->toDateString(), 'A');

        $allocations = $this->stock->stockOut($this->item, 4, $this->sale());

        $this->assertCount(2, $allocations);
        $this->assertEquals([2, 2], array_column($allocations, 'qty'));
    }

    public function test_an_expired_batch_is_never_auto_picked()
    {
        $expired = $this->receive(5, now()->subDay()->toDateString(), 'OLD');

        $this->stock->stockOut($this->item, 2, $this->sale());

        $this->assertEquals(5, $expired->fresh()->qty_remaining);
        $this->assertEquals(3, $this->onHand());
        $this->assertEquals(-2, $this->stock->sellableQty($this->item->fresh()));
        $this->assertSame([], $this->stock->verify());
    }

    public function test_selling_5_with_3_in_stock_leaves_minus_2_then_receiving_10_leaves_8_in_the_new_batch()
    {
        $this->receive(3, now()->addDays(60)->toDateString(), 'A');

        $this->stock->stockOut($this->item, 5, $this->sale());
        $this->assertEquals(-2, $this->onHand());

        $newBatch = $this->receive(10, now()->addDays(120)->toDateString(), 'C');

        $this->assertEquals(8, $this->onHand());
        $this->assertEquals(8, $newBatch->qty_remaining);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_reverse_returns_stock_to_the_same_batches_and_is_idempotent()
    {
        $batch = $this->receive(10, now()->addDays(60)->toDateString(), 'A');
        $invoice = $this->sale();
        $this->stock->stockOut($this->item, 4, $invoice);

        $this->stock->reverse($invoice);
        $this->stock->reverse($invoice);

        $this->assertEquals(10, $batch->fresh()->qty_remaining);
        $this->assertEquals(10, $this->onHand());
        $this->assertSame([], $this->stock->verify());
    }

    public function test_reversing_a_stock_in_whose_stock_was_used_is_refused()
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);
        $batch = $this->stock->stockIn($this->item, 5, 'A', now()->addDays(60), 40, $adjustment, null, 'adjustment_in');
        $this->stock->stockOut($this->item, 3, $this->sale());

        try {
            $this->stock->reverse($adjustment);
            $this->fail('Expected the reversal to be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been used', $e->getMessage());
        }

        $this->assertEquals(2, $batch->fresh()->qty_remaining);
        $this->assertEquals(2, $this->onHand());
    }

    public function test_reversing_a_negative_sale_after_its_shortfall_was_settled_puts_stock_back_in_the_settling_batch()
    {
        $invoice = $this->sale();
        $this->stock->stockOut($this->item, 5, $invoice);
        $batch = $this->receive(10, now()->addDays(60)->toDateString(), 'A');
        $this->assertEquals(5, $batch->qty_remaining);

        $this->stock->reverse($invoice);

        $this->assertEquals(10, $this->onHand());
        $this->assertEquals(10, $batch->fresh()->qty_remaining);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_reversing_an_unsettled_negative_sale_clears_the_shortfall()
    {
        $invoice = $this->sale();
        $this->stock->stockOut($this->item, 4, $invoice);
        $this->assertEquals(-4, $this->onHand());

        $this->stock->reverse($invoice);

        $this->assertEquals(0, $this->onHand());
        $this->assertSame([], $this->stock->verify());
    }

    public function test_the_same_batch_no_and_expiry_tops_up_the_existing_batch()
    {
        $expiry = now()->addDays(60)->toDateString();
        $first = $this->receive(4, $expiry, 'LOT1');
        $second = $this->receive(6, $expiry, 'LOT1');

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(10, $second->qty_remaining);
        $this->assertEquals(1, ItemBatch::count());
    }

    public function test_document_numbers_follow_the_invoice_style()
    {
        $year = now()->format('y');

        $this->assertEquals("PO-{$year}00001", DocumentSequence::next('PO'));
        $this->assertEquals("PO-{$year}00002", DocumentSequence::next('PO'));
        $this->assertEquals("GRN-{$year}00001", DocumentSequence::next('GRN'));
    }

    public function test_inventory_verify_command_reports_zero_mismatches()
    {
        $this->receive(3, now()->addDays(60)->toDateString(), 'A');
        $this->stock->stockOut($this->item, 5, $this->sale());
        $this->receive(10, null, 'B');

        \Illuminate\Support\Carbon::setTestNow('2026-10-18 00:00:00');
        $this->artisan('inventory:verify')->expectsOutput('[2026-10-18 00:00:00] Inventory OK: 0 mismatches.')->assertExitCode(0);
        \Illuminate\Support\Carbon::setTestNow();
    }
}
