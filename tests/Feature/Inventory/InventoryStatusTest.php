<?php

namespace Tests\Feature\Inventory;

use App\Http\Livewire\Admins\Dashboard;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemBatch;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryStatusTest extends TestCase
{
    use RefreshDatabase;

    private const EXPIRED_BADGE = '<span class="jfr-badge jfr-badge-negative">Expired</span>';
    private const EXPIRING_BADGE = '<span class="jfr-badge jfr-badge-expiring">Expiring</span>';
    private const LOW_BADGE = '<span class="jfr-badge jfr-badge-low">Low</span>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function item(string $code, int $threshold = 10): InventoryItem
    {
        return InventoryItem::create(['code' => $code, 'name' => "Item $code", 'cost_price' => 10, 'sale_price' => 20, 'low_stock_threshold' => $threshold]);
    }

    private function batch(InventoryItem $item, int $qty, ?string $expiry, string $batchNo): ItemBatch
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);

        return app(StockService::class)->stockIn($item, $qty, $batchNo, $expiry, 10, $adjustment, null, 'adjustment_in');
    }

    private function inDays(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }

    private function itemsPage()
    {
        return $this->get(route('admin_inventory_items'))->assertOk();
    }

    // ---- Expiry boundaries ----

    public function test_a_batch_expiring_on_day_30_is_expiring()
    {
        $batch = $this->batch($this->item('D30', 0), 5, $this->inDays(30), 'D30');

        $this->assertTrue($batch->is_expiring_soon);
        $this->assertFalse($batch->is_expired);
        $this->assertEquals(1, ItemBatch::expiringWithStock()->count());
        $this->itemsPage()->assertSee(self::EXPIRING_BADGE, false);
    }

    public function test_a_batch_expiring_on_day_31_is_not_expiring()
    {
        $batch = $this->batch($this->item('D31', 0), 5, $this->inDays(31), 'D31');

        $this->assertFalse($batch->is_expiring_soon);
        $this->assertEquals(0, ItemBatch::expiringWithStock()->count());
        $this->itemsPage()->assertDontSee(self::EXPIRING_BADGE, false);
    }

    public function test_a_batch_expiring_today_is_expiring_not_expired_and_still_usable()
    {
        $item = $this->item('D0', 0);
        $batch = $this->batch($item, 5, $this->inDays(0), 'D0');

        $this->assertTrue($batch->is_expiring_soon);
        $this->assertFalse($batch->is_expired);
        $this->assertEquals(0, ItemBatch::expiredWithStock()->count());
        $this->assertEquals(5, app(StockService::class)->sellableQty($item));
    }

    public function test_a_batch_that_expired_yesterday_is_expired_not_expiring()
    {
        $batch = $this->batch($this->item('DM1', 0), 5, $this->inDays(-1), 'DM1');

        $this->assertTrue($batch->is_expired);
        $this->assertFalse($batch->is_expiring_soon);
        $this->assertEquals(1, ItemBatch::expiredWithStock()->count());
        $this->assertEquals(0, ItemBatch::expiringWithStock()->count());

        $this->itemsPage()
            ->assertSee(self::EXPIRED_BADGE, false)
            ->assertDontSee(self::EXPIRING_BADGE, false);
    }

    public function test_a_used_up_expired_batch_raises_no_badge()
    {
        $item = $this->item('USED', 0);
        $batch = $this->batch($item, 5, $this->inDays(-1), 'OLD');
        app(StockService::class)->stockOut($item, 5, StockAdjustment::first(), $batch->id, null, 'adjustment_out');

        $this->assertEquals(0, ItemBatch::expiredWithStock()->count());
        $this->itemsPage()->assertDontSee(self::EXPIRED_BADGE, false);
    }

    public function test_an_item_with_both_expired_and_expiring_stock_shows_both_badges()
    {
        $item = $this->item('BOTH', 0);
        $this->batch($item, 3, $this->inDays(-5), 'OLD');
        $this->batch($item, 4, $this->inDays(10), 'SOON');

        $this->itemsPage()
            ->assertSee(self::EXPIRED_BADGE, false)
            ->assertSee(self::EXPIRING_BADGE, false);
    }

    // ---- Usable qty ----

    public function test_the_list_shows_usable_qty_beside_on_hand_when_they_differ()
    {
        $item = $this->item('LID', 3);
        $this->batch($item, 10, $this->inDays(-13), 'LD-OLD');
        $this->batch($item, 5, $this->inDays(300), 'LD-NEW');

        $this->itemsPage()->assertSee('15')->assertSee('(5 usable)');
    }

    public function test_usable_qty_is_hidden_when_it_equals_on_hand()
    {
        $item = $this->item('OK', 3);
        $this->batch($item, 8, $this->inDays(300), 'A');

        $this->itemsPage()->assertDontSee('usable)');
    }

    public function test_usable_qty_includes_an_unsettled_shortfall()
    {
        $item = $this->item('NEG', 0);
        $this->batch($item, 3, $this->inDays(300), 'A');
        $patient = patient::create(['name' => 'P', 'email' => 'p@x.com', 'phone' => '1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+']);
        app(StockService::class)->stockOut($item, 5, Invoice::create(['invoice_number' => 'X1', 'patient_id' => $patient->id]));

        $usable = InventoryItem::withUsableQty()->find($item->id)->usable_qty;

        $this->assertEquals(-2, (int) $usable);
        $this->assertEquals(app(StockService::class)->sellableQty($item->fresh()), (int) $usable);
    }

    // ---- Low stock is based on usable qty ----

    public function test_an_item_with_plenty_on_hand_but_mostly_expired_is_low()
    {
        $item = $this->item('LID', 10);
        $this->batch($item, 10, $this->inDays(-13), 'LD-OLD');
        $this->batch($item, 5, $this->inDays(300), 'LD-NEW');

        $this->assertEquals(15, $item->fresh()->qty_on_hand);
        $this->assertTrue(InventoryItem::lowStock()->whereKey($item->id)->exists());
        $this->itemsPage()->assertSee(self::LOW_BADGE, false);
    }

    public function test_an_item_with_enough_usable_stock_is_not_low()
    {
        $item = $this->item('SYR', 10);
        $this->batch($item, 11, $this->inDays(300), 'A');

        $this->assertFalse(InventoryItem::lowStock()->whereKey($item->id)->exists());
        $this->itemsPage()->assertDontSee(self::LOW_BADGE, false);
    }

    public function test_the_low_stock_filter_uses_usable_qty()
    {
        $low = $this->item('LOWX', 10);
        $this->batch($low, 20, $this->inDays(-1), 'OLD');
        $this->batch($low, 2, $this->inDays(300), 'NEW');
        $fine = $this->item('FINE', 10);
        $this->batch($fine, 20, $this->inDays(300), 'A');

        Livewire::test(\App\Http\Livewire\Admins\Inventory\Items::class)
            ->set('filter_status', 'low')
            ->call('$refresh')
            ->assertSee('Item LOWX')
            ->assertDontSee('Item FINE');
    }

    public function test_the_bell_and_dashboard_count_low_stock_by_usable_qty()
    {
        $mostlyExpired = $this->item('LID', 10);
        $this->batch($mostlyExpired, 10, $this->inDays(-13), 'OLD');
        $this->batch($mostlyExpired, 5, $this->inDays(300), 'NEW');
        $healthy = $this->item('SYR', 10);
        $this->batch($healthy, 50, $this->inDays(300), 'A');

        $this->itemsPage()->assertSee('<span class="jf-navbar-badge">1</span>', false);

        Livewire::test(Dashboard::class)->assertViewHas('lowStockCount', 1);
    }

    public function test_inactive_items_are_never_counted_as_low()
    {
        $item = $this->item('OFF', 10);
        $item->update(['is_active' => false]);

        $this->assertEquals(0, InventoryItem::lowStock()->count());
    }
}
