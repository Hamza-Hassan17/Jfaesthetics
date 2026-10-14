<?php

namespace Tests\Feature\Inventory;

use App\Http\Livewire\Admins\Inventory\Receiving;
use App\Http\Livewire\Admins\Inventory\SupplierPayments;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\PayablesService;
use App\Services\Inventory\PurchasingService;
use App\Services\Inventory\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    private PurchasingService $purchasing;
    private PayablesService $payables;
    private StockService $stock;
    private Supplier $supplier;
    private InventoryItem $syringe;
    private InventoryItem $gloves;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->purchasing = app(PurchasingService::class);
        $this->payables = app(PayablesService::class);
        $this->stock = app(StockService::class);

        $this->supplier = Supplier::create(['name' => 'MedSupply', 'credit_days' => 15]);
        $this->syringe = InventoryItem::create(['code' => 'SYR', 'name' => 'Syringe 3ml', 'cost_price' => 40, 'sale_price' => 50]);
        $this->gloves = InventoryItem::create(['code' => 'GLV', 'name' => 'Gloves', 'cost_price' => 15, 'sale_price' => 0]);
    }

    private function orderedPo(array $lines): PurchaseOrder
    {
        $po = $this->purchasing->savePurchaseOrder(
            ['supplier_id' => $this->supplier->id, 'order_date' => now()->toDateString(), 'expected_date' => null, 'discount' => 0, 'other_charges' => 0, 'notes' => null],
            $lines
        );
        $this->purchasing->markOrdered($po);

        return $po->fresh('items');
    }

    private function receiveAgainst(PurchaseOrder $po, array $qtyByLine, array $extra = []): GoodsReceipt
    {
        $lines = $po->items->map(fn ($line) => [
            'purchase_order_item_id' => $line->id,
            'item_id' => $line->item_id,
            'qty' => $qtyByLine[$line->item_id] ?? 0,
            'unit_cost' => $line->unit_cost,
            'batch_no' => null,
            'expiry_date' => now()->addYear()->toDateString(),
        ])->all();

        return $this->purchasing->receive(array_merge(['receive_date' => now()->toDateString(), 'due_date' => null], $extra), $lines, $po);
    }

    private function directReceive(InventoryItem $item, int $qty, float $cost, array $extra = []): GoodsReceipt
    {
        return $this->purchasing->receive(
            array_merge(['supplier_id' => $this->supplier->id, 'receive_date' => now()->toDateString(), 'due_date' => null], $extra),
            [['purchase_order_item_id' => null, 'item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost, 'batch_no' => null, 'expiry_date' => null]]
        );
    }

    private function sell(InventoryItem $item, int $qty): Invoice
    {
        $patient = patient::create(['name' => 'P', 'email' => uniqid() . '@x.com', 'phone' => '1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+']);
        $invoice = Invoice::create(['invoice_number' => uniqid(), 'patient_id' => $patient->id]);
        $this->stock->stockOut($item, $qty, $invoice);

        return $invoice;
    }

    private function pay(float $amount, ?array $allocations = null): SupplierPayment
    {
        $allocations ??= $this->payables->suggestAllocations($this->supplier->fresh(), $amount);

        return $this->payables->savePayment(['supplier_id' => $this->supplier->id, 'payment_date' => now()->toDateString(), 'amount' => $amount, 'mode' => 'cash'], $allocations);
    }

    // ---- Spec acceptance ----

    public function test_receiving_60_of_100_sets_partially_received_adds_60_to_stock_and_to_the_supplier_balance()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);

        $grn = $this->receiveAgainst($po, [$this->syringe->id => 60]);

        $this->assertEquals('partially_received', $po->fresh()->status);
        $this->assertEquals(60, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(2400, (float) $grn->total);
        $this->assertEquals(2400, $this->payables->balance($this->supplier));
    }

    public function test_receiving_the_remaining_40_sets_received()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $this->receiveAgainst($po, [$this->syringe->id => 60]);
        $this->receiveAgainst($po->fresh('items'), [$this->syringe->id => 40]);

        $this->assertEquals('received', $po->fresh()->status);
        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(4000, $this->payables->balance($this->supplier));
    }

    public function test_a_50000_payment_against_two_30000_grns_pays_the_first_and_part_pays_the_second()
    {
        $first = $this->directReceive($this->syringe, 750, 40);
        $second = $this->directReceive($this->syringe, 750, 40);

        $this->pay(50000);

        $this->assertEquals('paid', $first->fresh()->payment_status);
        $this->assertEquals('partial', $second->fresh()->payment_status);
        $this->assertEquals(20000, (float) $second->fresh()->amount_paid);
        $this->assertEquals(10000, $this->payables->balance($this->supplier));
    }

    // ---- Purchase orders ----

    public function test_over_receiving_is_blocked()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40]]);

        $this->expectException(DomainException::class);
        $this->receiveAgainst($po, [$this->syringe->id => 11]);
    }

    public function test_a_draft_po_cannot_be_received_against()
    {
        $po = $this->purchasing->savePurchaseOrder(['supplier_id' => $this->supplier->id, 'order_date' => now()->toDateString(), 'expected_date' => null, 'discount' => 0, 'other_charges' => 0], [['item_id' => $this->syringe->id, 'qty_ordered' => 5, 'unit_cost' => 40]]);

        $this->expectException(DomainException::class);
        $this->receiveAgainst($po->fresh('items'), [$this->syringe->id => 5]);
    }

    public function test_an_ordered_po_allows_adding_lines_and_increasing_qty_but_not_reducing_or_removing()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40]]);
        $line = $po->items->first();
        $header = ['supplier_id' => $this->supplier->id, 'order_date' => now()->toDateString(), 'expected_date' => null, 'discount' => 0, 'other_charges' => 0];

        $po = $this->purchasing->savePurchaseOrder($header, [
            ['id' => $line->id, 'item_id' => $this->syringe->id, 'qty_ordered' => 15, 'unit_cost' => 40],
            ['id' => null, 'item_id' => $this->gloves->id, 'qty_ordered' => 20, 'unit_cost' => 15],
        ], $po);
        $this->assertEquals(2, $po->items->count());
        $this->assertEquals(15 * 40 + 20 * 15, (float) $po->total);

        try {
            $this->purchasing->savePurchaseOrder($header, [['id' => $line->id, 'item_id' => $this->syringe->id, 'qty_ordered' => 9, 'unit_cost' => 40]], $po);
            $this->fail('Reducing qty should be refused.');
        } catch (DomainException $e) {
            $this->assertEquals(15, $line->fresh()->qty_ordered);
        }
    }

    public function test_cancel_is_only_allowed_before_anything_is_received()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40]]);
        $this->receiveAgainst($po, [$this->syringe->id => 4]);

        $this->expectException(DomainException::class);
        $this->purchasing->cancel($po->fresh());
    }

    public function test_close_short_keeps_qty_ordered_records_the_reason_and_stops_receiving()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $this->receiveAgainst($po, [$this->syringe->id => 60]);

        $this->purchasing->closeShort($po->fresh(), 'Supplier discontinued the item');

        $po = $po->fresh('items');
        $this->assertEquals('closed', $po->status);
        $this->assertEquals('Supplier discontinued the item', $po->closed_reason);
        $this->assertNotNull($po->closed_at);
        $this->assertEquals(100, $po->items->first()->qty_ordered);
        $this->assertEquals(60, $po->items->first()->qty_received);
        $this->assertEquals(40, $po->items->first()->qty_outstanding);

        $this->expectException(DomainException::class);
        $this->receiveAgainst($po, [$this->syringe->id => 10]);
    }

    public function test_close_short_requires_a_partially_received_po_and_a_reason()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40]]);

        try {
            $this->purchasing->closeShort($po, 'No longer needed');
            $this->fail('Closing an ordered P.O with nothing received should be refused.');
        } catch (DomainException $e) {
        }

        $this->receiveAgainst($po, [$this->syringe->id => 4]);
        $this->expectException(DomainException::class);
        $this->purchasing->closeShort($po->fresh(), '  ');
    }

    public function test_the_po_print_shows_ordered_received_and_closed_short()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $this->receiveAgainst($po, [$this->syringe->id => 60]);
        $this->purchasing->closeShort($po->fresh(), 'Out of stock at supplier');

        $this->get(route('admin_inventory_po_print', $po->id))
            ->assertOk()
            ->assertSee('Closed short')
            ->assertSee('Out of stock at supplier')
            ->assertSeeInOrder(['100', '60', '40']);
    }

    // ---- GRN ----

    public function test_blank_batch_no_becomes_grn_no_dash_line_no_and_each_line_gets_its_own_batch()
    {
        $po = $this->orderedPo([
            ['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40],
            ['item_id' => $this->gloves->id, 'qty_ordered' => 10, 'unit_cost' => 15],
        ]);

        $grn = $this->receiveAgainst($po, [$this->syringe->id => 10, $this->gloves->id => 10]);

        $this->assertEquals(["{$grn->grn_no}-1", "{$grn->grn_no}-2"], $grn->items->pluck('batch_no')->all());
        $this->assertEquals(2, $grn->items->pluck('batch_id')->unique()->count());
    }

    public function test_due_date_defaults_to_receive_date_plus_credit_days()
    {
        $grn = $this->directReceive($this->syringe, 5, 40, ['receive_date' => '2026-10-01']);

        $this->assertEquals('2026-10-16', $grn->due_date->toDateString());
    }

    public function test_expiry_is_required_unless_no_expiry_is_ticked()
    {
        $component = Livewire::test(Receiving::class)
            ->call('show_direct_form')
            ->set('supplier_id', $this->supplier->id)
            ->set('lines.0.item_id', $this->gloves->id)
            ->set('lines.0.qty', 5)
            ->call('save')
            ->assertHasErrors('lines.0.expiry_date');
        $this->assertEquals(0, GoodsReceipt::count());

        $component->set('lines.0.no_expiry', true)->call('save')->assertHasNoErrors();
        $this->assertNull(GoodsReceipt::first()->items->first()->expiry_date);
    }

    public function test_discount_and_other_charges_change_the_grn_total_but_not_batch_cost()
    {
        $grn = $this->directReceive($this->syringe, 100, 40, ['discount' => 500, 'other_charges' => 200]);

        $this->assertEquals(3700, (float) $grn->total);
        $this->assertEquals(40, (float) $grn->items->first()->batch->unit_cost);
        $this->assertEquals(40, (float) $this->syringe->fresh()->cost_price);
    }

    public function test_receiving_updates_item_cost_to_the_latest_unit_cost()
    {
        $this->directReceive($this->syringe, 10, 45);

        $this->assertEquals(45, (float) $this->syringe->fresh()->cost_price);
    }

    // ---- GRN edit rules ----

    public function test_bill_no_due_date_and_notes_are_always_editable()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 4);
        $line = $grn->items->first();

        $this->purchasing->updateReceipt($grn, ['supplier_bill_no' => 'B-77', 'due_date' => '2026-12-31', 'notes' => 'Checked'], [
            $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => null, 'qty' => 10, 'unit_cost' => 40],
        ]);

        $grn->refresh();
        $this->assertEquals('B-77', $grn->supplier_bill_no);
        $this->assertEquals('2026-12-31', $grn->due_date->toDateString());
        $this->assertEquals('Checked', $grn->notes);
    }

    public function test_batch_no_and_expiry_are_editable_after_stock_is_used()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 4);
        $line = $grn->items->first();

        $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => 'LOT-FIXED', 'expiry_date' => '2028-01-31', 'qty' => 10, 'unit_cost' => 40],
        ]);

        $this->assertEquals('LOT-FIXED', $line->fresh()->batch_no);
        $this->assertEquals('LOT-FIXED', $line->batch->fresh()->batch_no);
        $this->assertEquals('2028-01-31', $line->batch->fresh()->expiry_date->toDateString());
        $this->assertEquals(6, $line->batch->fresh()->qty_remaining);
    }

    public function test_qty_and_unit_cost_are_editable_while_the_batch_is_unused()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $grn = $this->receiveAgainst($po, [$this->syringe->id => 60]);
        $line = $grn->items->first();

        $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => $line->expiry_date->toDateString(), 'qty' => 50, 'unit_cost' => 42],
        ]);

        $line->refresh();
        $this->assertEquals(50, $line->qty);
        $this->assertEquals(42, (float) $line->unit_cost);
        $this->assertEquals($grn->items->first()->batch_id, $line->batch_id, 'Edit re-applies into the same batch.');
        $this->assertEquals(50, $line->batch->qty_remaining);
        $this->assertEquals(50, $line->batch->qty_received);
        $this->assertEquals(42, (float) $line->batch->unit_cost);
        $this->assertEquals(50, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(50, $po->items->first()->fresh()->qty_received);
        $this->assertEquals(2100, (float) $grn->fresh()->total);
        $this->assertEquals(2100, $this->payables->balance($this->supplier));
        $this->assertSame([], $this->stock->verify());
    }

    public function test_qty_and_unit_cost_are_locked_once_the_batch_is_used()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 3);
        $line = $grn->items->first();

        try {
            $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
                $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => null, 'qty' => 8, 'unit_cost' => 40],
            ]);
            $this->fail('Changing qty on a used batch should be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been used', $e->getMessage());
        }

        $this->assertEquals(10, $line->fresh()->qty);
        $this->assertEquals(7, $line->batch->fresh()->qty_remaining);
        $this->assertEquals(7, $this->syringe->fresh()->qty_on_hand);

        $this->assertTrue($this->purchasing->isLineUsed($line->fresh()));
    }

    public function test_editing_qty_after_a_shortfall_settlement_keeps_stock_consistent()
    {
        $this->sell($this->syringe, 4);
        $grn = $this->directReceive($this->syringe, 10, 40);
        $line = $grn->items->first();
        $this->assertEquals(6, $this->syringe->fresh()->qty_on_hand);

        $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => null, 'qty' => 12, 'unit_cost' => 40],
        ]);

        $this->assertEquals(8, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(8, $line->batch->fresh()->qty_remaining);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_lowering_a_paid_grn_total_turns_the_excess_into_advance()
    {
        $grn = $this->directReceive($this->syringe, 100, 40);
        $payment = $this->pay(4000);
        $line = $grn->items->first();

        $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => null, 'qty' => 80, 'unit_cost' => 40],
        ]);

        $this->assertEquals('paid', $grn->fresh()->payment_status);
        $this->assertEquals(3200, (float) $grn->fresh()->amount_paid);
        $this->assertEquals(800, $payment->fresh('allocations')->unallocated);
        $this->assertEquals(-800, $this->payables->balance($this->supplier));
    }

    public function test_a_grn_with_used_stock_cannot_be_deleted()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 1);

        $this->expectException(DomainException::class);
        $this->purchasing->deleteReceipt($grn);
    }

    public function test_deleting_an_unused_grn_reverses_stock_releases_payments_and_reopens_the_po()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $grn = $this->receiveAgainst($po, [$this->syringe->id => 100]);
        $payment = $this->pay(1000);
        $this->assertEquals('received', $po->fresh()->status);

        $this->purchasing->deleteReceipt($grn);

        $this->assertSoftDeleted('goods_receipts', ['id' => $grn->id]);
        $this->assertEquals(0, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals('ordered', $po->fresh()->status);
        $this->assertEquals(0, $po->items->first()->fresh()->qty_received);
        $this->assertEquals(1000, $payment->fresh('allocations')->unallocated);
        $this->assertSame([], $this->stock->verify());
    }

    // ---- Payments ----

    public function test_the_opening_balance_is_paid_off_first()
    {
        $this->supplier->update(['opening_balance' => 10000]);
        $grn = $this->directReceive($this->syringe, 500, 40);

        $payment = $this->pay(15000);

        $this->assertEquals(10000, (float) $payment->allocations->whereNull('goods_receipt_id')->sum('amount'));
        $this->assertEquals(5000, (float) $grn->fresh()->amount_paid);
        $this->assertEquals('partial', $grn->fresh()->payment_status);
        $this->assertEquals(15000, $this->payables->balance($this->supplier));
    }

    public function test_an_advance_auto_applies_to_the_next_grn()
    {
        $payment = $this->pay(5000);
        $this->assertEquals(5000, $payment->unallocated);

        $grn = $this->directReceive($this->syringe, 200, 40);

        $this->assertEquals(5000, (float) $grn->amount_paid);
        $this->assertEquals('partial', $grn->payment_status);
        $this->assertEquals(0, $payment->fresh('allocations')->unallocated);
    }

    public function test_allocations_cannot_exceed_the_payment_or_a_bills_outstanding()
    {
        $grn = $this->directReceive($this->syringe, 100, 40);

        try {
            $this->pay(1000, [(string) $grn->id => 1500]);
            $this->fail('Allocating more than the payment should be refused.');
        } catch (DomainException $e) {
        }

        $this->expectException(DomainException::class);
        $this->pay(9000, [(string) $grn->id => 5000]);
    }

    public function test_editing_and_deleting_a_payment_recomputes_grn_status()
    {
        $grn = $this->directReceive($this->syringe, 100, 40);
        $payment = $this->pay(4000);
        $this->assertEquals('paid', $grn->fresh()->payment_status);

        $this->payables->savePayment(['payment_date' => now()->toDateString(), 'amount' => 1500, 'mode' => 'cheque'], [(string) $grn->id => 1500], $payment);
        $this->assertEquals('partial', $grn->fresh()->payment_status);
        $this->assertEquals(1500, (float) $grn->fresh()->amount_paid);

        $this->payables->deletePayment($payment->fresh());
        $this->assertEquals('unpaid', $grn->fresh()->payment_status);
        $this->assertEquals(0, (float) $grn->fresh()->amount_paid);
    }

    public function test_the_payment_form_pre_fills_allocations_oldest_first()
    {
        $this->supplier->update(['opening_balance' => 1000]);
        $first = $this->directReceive($this->syringe, 50, 40, ['receive_date' => now()->subDays(5)->toDateString()]);
        $second = $this->directReceive($this->syringe, 50, 40);

        Livewire::test(SupplierPayments::class)
            ->call('show_create_form')
            ->set('supplier_id', $this->supplier->id)
            ->set('amount', 2500)
            ->assertSet('allocations', [PayablesService::OPENING => 1000.0, 'g' . $first->id => 1500.0])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('partial', $first->fresh()->payment_status);
        $this->assertEquals('unpaid', $second->fresh()->payment_status);
    }

    // ---- Ledger ----

    public function test_the_supplier_ledger_runs_a_balance_by_date()
    {
        $this->supplier->update(['opening_balance' => 1000]);
        $this->directReceive($this->syringe, 50, 40, ['receive_date' => '2026-10-01']);
        $this->payables->savePayment(['supplier_id' => $this->supplier->id, 'payment_date' => '2026-10-05', 'amount' => 1500, 'mode' => 'cash'], []);
        $this->directReceive($this->syringe, 25, 40, ['receive_date' => '2026-10-10']);

        $rows = $this->payables->ledger($this->supplier->fresh());
        $this->assertEquals([1000, 3000, 1500, 2500], array_column($rows, 'balance'));

        $fromOct6 = $this->payables->ledger($this->supplier->fresh(), '2026-10-06');
        $this->assertEquals('Balance brought forward', $fromOct6[0]['document']);
        $this->assertEquals([1500, 2500], array_column($fromOct6, 'balance'));
    }

    public function test_ledger_print_and_csv_render()
    {
        $this->directReceive($this->syringe, 50, 40);

        $this->get(route('admin_inventory_supplier_ledger_print', $this->supplier->id))->assertOk()->assertSee('Supplier Ledger');
        $csv = $this->get(route('admin_inventory_supplier_ledger_csv', $this->supplier->id));
        $csv->assertOk();
        $this->assertStringContainsString('2000.00', $csv->streamedContent());
    }

    // ---- Access ----

    public function test_purchasing_tabs_need_view_cost_or_the_matching_manage_permission()
    {
        $nurse = User::factory()->create(['role_id' => Role::where('name', 'Nurse')->value('id')]);
        $accountant = User::factory()->create(['role_id' => Role::where('name', 'Accountant')->value('id')]);

        foreach (['admin_inventory_suppliers', 'admin_inventory_purchase_orders', 'admin_inventory_receiving', 'admin_inventory_supplier_payments'] as $route) {
            $this->actingAs($nurse)->get(route($route))->assertForbidden();
            $this->actingAs($accountant)->get(route($route))->assertOk();
        }

        $this->actingAs($nurse)->get(route('admin_inventory_items'))->assertOk()->assertDontSee('Purchase Orders</a>', false);
    }

    public function test_print_and_csv_routes_are_blocked_server_side_without_access()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 10, 'unit_cost' => 40]]);
        $grn = $this->receiveAgainst($po, [$this->syringe->id => 10]);
        $nurse = User::factory()->create(['role_id' => Role::where('name', 'Nurse')->value('id')]);

        $this->actingAs($nurse);
        $this->get(route('admin_inventory_po_print', $po->id))->assertForbidden();
        $this->get(route('admin_inventory_grn_print', $grn->id))->assertForbidden();
        $this->get(route('admin_inventory_supplier_ledger_print', $this->supplier->id))->assertForbidden();
        $this->get(route('admin_inventory_supplier_ledger_csv', $this->supplier->id))->assertForbidden();
    }

    public function test_access_is_rechecked_on_every_livewire_request_not_just_page_load()
    {
        $accountant = User::factory()->create(['role_id' => Role::where('name', 'Accountant')->value('id')]);
        $nurse = User::factory()->create(['role_id' => Role::where('name', 'Nurse')->value('id')]);

        foreach ([
            \App\Http\Livewire\Admins\Inventory\Suppliers::class,
            \App\Http\Livewire\Admins\Inventory\PurchaseOrders::class,
            Receiving::class,
            SupplierPayments::class,
        ] as $component) {
            $test = Livewire::actingAs($accountant)->test($component)->assertOk();

            $this->actingAs($nurse);
            $test->call('show_index')->assertForbidden();
        }
    }

    // ---- Audit log ----

    public function test_editing_batch_no_and_expiry_on_used_stock_is_audit_logged_with_old_values()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 4);
        $line = $grn->items->first();
        $oldBatch = $line->batch_no;

        $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => 'LOT-FIXED', 'expiry_date' => '2028-01-31', 'qty' => 10, 'unit_cost' => 40],
        ]);

        $log = \App\Models\ActivityLog::where('module', 'inventory')->where('record_id', $grn->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals(auth()->id(), $log->user_id);
        $this->assertStringContainsString("batch no '{$oldBatch}' → 'LOT-FIXED'", $log->description);
        $this->assertStringContainsString("expiry 'none' → '2028-01-31'", $log->description);
        $this->assertStringContainsString('[stock from this batch already used]', $log->description);
    }

    public function test_header_and_qty_edits_are_logged_and_unchanged_fields_are_not()
    {
        $grn = $this->directReceive($this->syringe, 10, 40, ['supplier_bill_no' => 'B-1']);
        $line = $grn->items->first();

        $this->purchasing->updateReceipt($grn, ['supplier_bill_no' => 'B-2', 'due_date' => $grn->due_date->toDateString()], [
            $line->id => ['batch_no' => $line->batch_no, 'expiry_date' => null, 'qty' => 8, 'unit_cost' => 40],
        ]);

        $descriptions = \App\Models\ActivityLog::where('record_id', $grn->id)->where('module', 'inventory')->pluck('description')->implode("\n");
        $this->assertStringContainsString("bill no 'B-1' → 'B-2'", $descriptions);
        $this->assertStringContainsString("qty '10' → '8'", $descriptions);
        $this->assertStringNotContainsString('due date', $descriptions);
        $this->assertStringNotContainsString('batch no', $descriptions);
        $this->assertStringNotContainsString('already used', $descriptions);
    }

    public function test_a_refused_edit_leaves_no_audit_row()
    {
        $grn = $this->directReceive($this->syringe, 10, 40);
        $this->sell($this->syringe, 1);
        $line = $grn->items->first();
        $before = \App\Models\ActivityLog::count();

        try {
            $this->purchasing->updateReceipt($grn, ['due_date' => $grn->due_date->toDateString()], [
                $line->id => ['batch_no' => 'NEW', 'expiry_date' => null, 'qty' => 5, 'unit_cost' => 40],
            ]);
        } catch (DomainException $e) {
        }

        $this->assertEquals($before, \App\Models\ActivityLog::count());
        $this->assertNotEquals('NEW', $line->fresh()->batch_no, 'The whole edit rolls back, including the batch label.');
    }

    public function test_inventory_stays_consistent_after_a_full_purchasing_cycle()
    {
        $po = $this->orderedPo([['item_id' => $this->syringe->id, 'qty_ordered' => 100, 'unit_cost' => 40]]);
        $grn = $this->receiveAgainst($po, [$this->syringe->id => 60]);
        $this->sell($this->syringe, 70);
        $this->receiveAgainst($po->fresh('items'), [$this->syringe->id => 40]);
        $this->pay(3000);

        $this->assertEquals(30, $this->syringe->fresh()->qty_on_hand);
        $this->assertSame([], app(StockService::class)->verify());
        \Illuminate\Support\Carbon::setTestNow('2026-10-18 00:00:00');
        $this->artisan('inventory:verify')->expectsOutput('[2026-10-18 00:00:00] Inventory OK: 0 mismatches.')->assertExitCode(0);
        \Illuminate\Support\Carbon::setTestNow();
    }
}
