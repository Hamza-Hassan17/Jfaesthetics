<?php

namespace Tests\Feature\Inventory;

use App\Http\Livewire\Admins\Invoices;
use App\Http\Livewire\Admins\Services;
use App\Models\ActivityLog;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemBatch;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ShortfallSettlement;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\InvoiceService;
use App\Services\Inventory\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private patient $patient;
    private Service $hifu;
    private InventoryItem $injection;
    private InventoryItem $syringe;
    private InventoryItem $gloves;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->actingAs(User::factory()->create());
        $this->stock = app(StockService::class);

        $this->patient = patient::create(['name' => 'Ramsha', 'email' => 'ramsha@example.com', 'phone' => '1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+']);
        $this->injection = InventoryItem::create(['code' => 'FDI', 'name' => 'Fat dissolving injection', 'cost_price' => 3000, 'sale_price' => 4500]);
        $this->syringe = InventoryItem::create(['code' => 'SYR', 'name' => 'Syringe 3ml', 'cost_price' => 20, 'sale_price' => 50]);
        $this->gloves = InventoryItem::create(['code' => 'GLV', 'name' => 'Gloves (pair)', 'cost_price' => 15, 'sale_price' => 0, 'default_billable' => false]);

        $this->hifu = Service::create(['name' => 'Hifu+MFU+RF+ Fat dissolving Inj', 'price' => 135000]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->injection->id, 'qty_per_session' => 1, 'is_billable' => true]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->syringe->id, 'qty_per_session' => 2, 'is_billable' => true]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->gloves->id, 'qty_per_session' => 1, 'is_billable' => false]);

        $this->receive($this->injection, 20, 'INJ-A', now()->addYear(), 3000);
        $this->receive($this->syringe, 100, 'SYR-A', now()->addYear(), 20);
        $this->receive($this->gloves, 100, 'GLV-A', null, 15);
    }

    private function receive(InventoryItem $item, int $qty, string $batch, $expiry, float $cost): ItemBatch
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);

        return $this->stock->stockIn($item, $qty, $batch, $expiry, $cost, $adjustment, null, 'adjustment_in');
    }

    /** New invoice with the Hifu package; recipe children are filled by the form. */
    private function hifuForm(int $sessions = 3)
    {
        return Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'service')
            ->set('items.0.session', $sessions)
            ->set('items.0.catalog_id', $this->hifu->id);
    }

    private function indexOf($component, InventoryItem $item): int
    {
        foreach ($component->get('items') as $i => $line) {
            if ((int) $line['catalog_id'] === $item->id && $line['type'] === 'product') {
                return $i;
            }
        }
        $this->fail("No line for {$item->name}");
    }

    private function line(Invoice $invoice, InventoryItem $item): InvoiceItem
    {
        return $invoice->fresh('items')->items->firstWhere('inventory_item_id', $item->id);
    }

    private function edit(Invoice $invoice)
    {
        return Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id);
    }

    // ---- Spec acceptance ----

    public function test_a_3_session_service_with_2_syringes_per_session_deducts_6_on_save()
    {
        $form = $this->hifuForm(3);
        $this->assertEquals(6, $form->get('items')[$this->indexOf($form, $this->syringe)]['quantity']);

        $form->call('generate_invoice')->assertHasNoErrors()->assertSet('_page', 'view');

        $invoice = Invoice::first();
        $service = $invoice->items->firstWhere('line_type', 'service');
        $syringe = $this->line($invoice, $this->syringe);

        $this->assertEquals(94, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(17, $this->injection->fresh()->qty_on_hand);
        $this->assertEquals(97, $this->gloves->fresh()->qty_on_hand);
        $this->assertEquals($service->id, $syringe->parent_line_id);
        $this->assertEquals(2, $syringe->qty_per_session);
        $this->assertEquals(6, $syringe->stock_qty);
    }

    public function test_the_service_price_is_the_package_price_and_only_billable_children_add_to_it()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::with('items')->first();

        $this->assertEquals(135000, (float) $invoice->items->firstWhere('line_type', 'service')->after_discount);
        $this->assertEquals(135000 + 3 * 4500 + 6 * 50, (float) $invoice->grand_total);
    }

    public function test_non_billable_lines_deduct_stock_but_are_not_on_the_patient_pdf_or_in_the_total()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();
        $gloves = $this->line($invoice, $this->gloves);

        $this->assertFalse($gloves->is_billable);
        $this->assertEquals(0, (float) $gloves->after_discount);
        $this->assertEquals(97, $this->gloves->fresh()->qty_on_hand);

        $this->get(route('admin_invoice_print', $invoice->id))->assertOk()->assertSee('Syringe 3ml')->assertDontSee('Gloves (pair)');
        Livewire::test(Invoices::class)->call('view', $invoice->id)->assertSee('Consumables used (not billed)')->assertSee('Gloves (pair)');
    }

    public function test_editing_an_invoice_from_3_to_1_injection_returns_2_to_the_same_batch()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();
        $batch = ItemBatch::where('batch_no', 'INJ-A')->first();
        $this->assertEquals(17, $batch->fresh()->qty_remaining);

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->injection) . '.quantity', 1)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(19, $batch->fresh()->qty_remaining);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_deleting_an_invoice_returns_all_its_stock()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();

        Livewire::test(Invoices::class)->call('prompt_delete', $invoice->id)->set('confirm_delete_password', 'password')->call('delete');

        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(20, $this->injection->fresh()->qty_on_hand);
        $this->assertEquals(100, $this->gloves->fresh()->qty_on_hand);
        $this->assertSame([], $this->stock->verify());
    }

    // ---- Recipe snapshot ----

    public function test_changing_a_recipe_after_billing_leaves_past_invoices_unchanged()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();

        ServiceItem::where('item_id', $this->syringe->id)->update(['qty_per_session' => 5]);

        $this->assertEquals(6, $this->line($invoice, $this->syringe)->quantity);
        $this->assertEquals(94, $this->syringe->fresh()->qty_on_hand);

        // Even recalculating sessions uses the line's own copy (2), not the new recipe (5).
        $form = $this->edit($invoice);
        $form->set('items.0.session', 4)->call('generate_invoice')->assertHasNoErrors();
        $this->assertEquals(8, $this->line($invoice, $this->syringe)->quantity);
    }

    public function test_changing_sessions_recalculates_children_and_deducts_the_extra_but_a_hand_edited_child_keeps_its_qty()
    {
        $form = $this->hifuForm(3);
        $form->set('items.' . $this->indexOf($form, $this->injection) . '.quantity', 4); // typed by hand
        $form->call('generate_invoice');
        $invoice = Invoice::first();

        $form = $this->edit($invoice);
        $form->set('items.0.session', 5)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(10, $this->line($invoice, $this->syringe)->quantity);
        $this->assertEquals(90, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(4, $this->line($invoice, $this->injection)->quantity);
        $this->assertTrue($this->line($invoice, $this->injection)->qty_overridden);
    }

    public function test_a_child_qty_edited_at_billing_is_what_is_deducted()
    {
        $form = $this->hifuForm(1);
        $form->set('items.' . $this->indexOf($form, $this->syringe) . '.quantity', 3)->call('generate_invoice');

        $this->assertEquals(97, $this->syringe->fresh()->qty_on_hand);
        $this->assertTrue($this->line(Invoice::first(), $this->syringe)->qty_overridden);
    }

    // ---- Editing lines in place ----

    public function test_reducing_a_line_returns_stock_to_its_own_batch_even_after_an_earlier_expiring_batch_arrives()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();
        $original = ItemBatch::where('batch_no', 'INJ-A')->first();
        $earlier = $this->receive($this->injection, 10, 'INJ-EARLY', now()->addMonth(), 2800);

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->injection) . '.quantity', 1)->call('generate_invoice');

        $this->assertEquals(19, $original->fresh()->qty_remaining);
        $this->assertEquals(10, $earlier->fresh()->qty_remaining);
    }

    public function test_increasing_a_line_takes_only_the_extra_and_leaves_existing_allocations()
    {
        $this->hifuForm(2)->call('generate_invoice');
        $invoice = Invoice::first();
        $original = ItemBatch::where('batch_no', 'INJ-A')->first();
        $earlier = $this->receive($this->injection, 10, 'INJ-EARLY', now()->addMonth(), 2800);

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->injection) . '.quantity', 3)->call('generate_invoice');

        $this->assertEquals(18, $original->fresh()->qty_remaining, 'The 2 already taken stay where they were.');
        $this->assertEquals(9, $earlier->fresh()->qty_remaining, 'The extra 1 comes FEFO from the earlier batch.');
    }

    public function test_changing_a_lines_item_reverses_the_old_item_and_takes_the_new_one()
    {
        Livewire::test(Invoices::class)->call('show_create_form')->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'product')->set('items.0.catalog_id', $this->syringe->id)->set('items.0.quantity', 5)
            ->call('generate_invoice');
        $invoice = Invoice::first();
        $lineId = $invoice->items->first()->id;

        $this->edit($invoice)->set('items.0.catalog_id', $this->injection->id)->set('items.0.quantity', 2)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(18, $this->injection->fresh()->qty_on_hand);
        $this->assertEquals($lineId, $invoice->fresh('items')->items->first()->id, 'The line keeps its id.');
    }

    public function test_removing_a_service_line_removes_its_children_and_returns_their_stock()
    {
        $form = $this->hifuForm(3);
        $form->call('add_item')->set('items.4.type', 'custom')->set('items.4.service', 'Consultation')->set('items.4.service_charges', 2000);
        $form->call('generate_invoice');
        $invoice = Invoice::first();

        $this->edit($invoice)->call('remove_item', 0)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(['Consultation'], $invoice->fresh('items')->items->pluck('service')->all());
        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(20, $this->injection->fresh()->qty_on_hand);
    }

    public function test_line_ids_are_stable_and_movements_point_at_the_right_line()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();
        $before = $invoice->items->pluck('id')->sort()->values()->all();

        $form = $this->edit($invoice);
        $form->set('items.0.session', 4)->call('generate_invoice');

        $this->assertEquals($before, $invoice->fresh('items')->items->pluck('id')->sort()->values()->all());
        $syringeLine = $this->line($invoice, $this->syringe);
        $this->assertEquals(8, -(int) StockMovement::where('reference_line_id', $syringeLine->id)->sum('qty'));
    }

    // ---- Insufficient stock and settlement ----

    public function test_a_recipe_needing_more_than_stock_warns_then_goes_negative_and_a_later_receipt_settles_and_recosts_it()
    {
        $low = InventoryItem::create(['code' => 'LOW', 'name' => 'Cannula', 'cost_price' => 100, 'sale_price' => 200]);
        $this->receive($low, 2, 'CAN-A', now()->addYear(), 100);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $low->id, 'qty_per_session' => 1, 'is_billable' => true]);

        $form = $this->hifuForm(3);
        $form->call('generate_invoice')->assertDispatchedBrowserEvent('invoice-shortfall-open');
        $this->assertEquals(0, Invoice::count());
        $form->call('generate_invoice', true)->assertSet('_page', 'view');

        $this->assertEquals(-1, $low->fresh()->qty_on_hand);
        $line = $this->line(Invoice::first(), $low);
        $this->assertEquals(100, (float) $line->unit_cost);

        $settling = $this->receive($low, 10, 'CAN-B', now()->addYear(), 130);

        $this->assertEquals(9, $low->fresh()->qty_on_hand);
        $this->assertEquals(9, $settling->fresh()->qty_remaining);
        $settlement = ShortfallSettlement::first();
        $this->assertEquals([$line->id, $settling->id, 1], [$settlement->owner_line_id, $settlement->batch_id, $settlement->qty]);
        $this->assertEquals(round((2 * 100 + 1 * 130) / 3, 2), (float) $line->fresh()->unit_cost, 'The settled unit is costed at the batch that covered it.');
        $this->assertSame([], $this->stock->verify());
    }

    public function test_child_unit_cost_comes_from_the_actual_batch_allocations()
    {
        $item = InventoryItem::create(['code' => 'MIX', 'name' => 'Mixed batch item', 'cost_price' => 999, 'sale_price' => 100]);
        $this->receive($item, 2, 'MIX-A', now()->addMonth(), 10);
        $this->receive($item, 5, 'MIX-B', now()->addYear(), 40);

        Livewire::test(Invoices::class)->call('show_create_form')->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'product')->set('items.0.catalog_id', $item->id)->set('items.0.quantity', 4)
            ->call('generate_invoice');

        $this->assertEquals(round((2 * 10 + 2 * 40) / 4, 2), (float) Invoice::first()->items->first()->unit_cost);
    }

    // ---- Paid invoices (D1) and payments (D2) ----

    private function paidHifu(): Invoice
    {
        $form = $this->hifuForm(5);
        $form->set('payments.0.amount', 135000 + 5 * 4500 + 10 * 50)->call('generate_invoice');

        return Invoice::first();
    }

    public function test_a_paid_invoice_cannot_be_edited_below_its_paid_amount()
    {
        $invoice = $this->paidHifu();

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->injection) . '.quantity', 2)->call('generate_invoice')->assertHasErrors('items');

        $this->assertEquals(5, $this->line($invoice, $this->injection)->quantity);
        $this->assertEquals(15, $this->injection->fresh()->qty_on_hand);
    }

    public function test_non_billable_edits_on_a_paid_invoice_still_save()
    {
        $invoice = $this->paidHifu();

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->gloves) . '.quantity', 2)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(98, $this->gloves->fresh()->qty_on_hand);
    }

    public function test_stock_only_reduction_returns_unused_stock_without_changing_what_was_billed()
    {
        $invoice = $this->paidHifu();
        $line = $this->line($invoice, $this->injection);
        $total = (float) $invoice->grand_total;

        Livewire::test(Invoices::class)->call('view', $invoice->id)
            ->call('prompt_reduce', $line->id)->set('reduce_qty', 3)->set('reduce_reason', 'Patient stopped after 2 of 5 sessions')
            ->call('reduce_stock')->assertHasNoErrors();

        $line->refresh();
        $this->assertEquals([5, 2], [$line->quantity, $line->stock_qty]);
        $this->assertEquals(18, $this->injection->fresh()->qty_on_hand);
        $this->assertEquals(18, ItemBatch::where('batch_no', 'INJ-A')->value('qty_remaining'));
        $this->assertEquals($total, (float) $invoice->fresh('items')->grand_total);
        $this->assertTrue(ActivityLog::where('description', 'like', '%stock-only reduction%used 5 → 2%Patient stopped after 2 of 5%')->exists());

        // A later edit of something else keeps the reduction.
        $this->edit($invoice)->set('notes', 'Checked')->call('generate_invoice')->assertHasNoErrors();
        $this->assertEquals(2, $line->fresh()->stock_qty);
        $this->assertEquals(18, $this->injection->fresh()->qty_on_hand);
    }

    public function test_stock_only_reduction_needs_a_reason_and_cannot_exceed_what_was_used()
    {
        $invoice = $this->paidHifu();
        $line = $this->line($invoice, $this->injection);
        $service = app(InvoiceService::class);

        try {
            $service->reduceStockUsed($line, 6, 'Too many');
            $this->fail('Reducing more than used should be refused.');
        } catch (DomainException $e) {
        }

        $this->expectException(DomainException::class);
        $service->reduceStockUsed($line, 1, ' ');
    }

    public function test_payments_survive_an_invoice_edit_with_the_same_ids_and_changes_are_audit_logged()
    {
        $invoice = $this->paidHifu();
        $paymentId = $invoice->payments->first()->id;

        $this->edit($invoice)->set('payments.0.payment_mode', 'Card')->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals([$paymentId], $invoice->fresh('payments')->payments->pluck('id')->all());
        $this->assertEquals('Card', $invoice->fresh('payments')->payments->first()->payment_mode);
        $this->assertTrue(ActivityLog::where('description', 'like', "%payment #{$paymentId}: mode 'Cash' → 'Card'%")->exists());
    }

    // ---- Audit ----

    public function test_line_changes_are_audit_logged_with_old_and_new_values_and_a_refused_edit_logs_nothing()
    {
        $this->hifuForm(3)->call('generate_invoice');
        $invoice = Invoice::first();

        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->syringe) . '.quantity', 7)->call('generate_invoice');
        $this->assertTrue(ActivityLog::where('module', 'invoices')->where('description', 'like', "%line 'Syringe 3ml': qty '6' → '7'%")->exists());

        $invoice->payments()->create(['paid_on' => now(), 'amount' => 999999, 'payment_mode' => 'Cash']);
        $before = ActivityLog::count();
        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->syringe) . '.quantity', 6)->call('generate_invoice')->assertHasErrors('items');
        $this->assertEquals($before, ActivityLog::count());
    }

    // ---- Existing invoices ----

    public function test_editing_a_pre_phase_3_invoice_does_not_create_children_or_move_stock()
    {
        $invoice = Invoice::create(['invoice_number' => 'OLD-1', 'patient_id' => $this->patient->id]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'line_type' => 'service', 'service_id' => $this->hifu->id, 'service' => $this->hifu->name,
            'quantity' => 1, 'session_number' => 3, 'service_charges' => 135000, 'discount_type' => 'flat', 'discount_value' => 0,
            'sub_total' => 135000, 'discount' => 0, 'after_discount' => 135000,
        ]);

        $this->edit($invoice)->set('notes', 'Re-saved')->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(1, $invoice->fresh('items')->items->count());
        $this->assertEquals(0, StockMovement::where('reference_type', $invoice->getMorphClass())->where('reference_id', $invoice->id)->count());
        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
    }

    // ---- Permissions ----

    public function test_editing_a_recipe_needs_manage_recipes()
    {
        $receptionist = User::factory()->create(['role_id' => Role::where('name', 'Receptionist')->value('id')]);
        $this->assertFalse($receptionist->hasPermission('inventory', 'manage_recipes'));

        Livewire::actingAs($receptionist)->test(Services::class)
            ->call('show_recipe', $this->hifu->id)
            ->set('recipe_lines.0.qty_per_session', 9)
            ->call('save_recipe')
            ->assertForbidden();

        Livewire::actingAs(User::factory()->create())->test(Services::class)
            ->call('show_recipe', $this->hifu->id)
            ->set('recipe_lines.0.qty_per_session', 9)
            ->call('save_recipe')
            ->assertHasNoErrors();
        $this->assertEquals(9, ServiceItem::where('item_id', $this->injection->id)->value('qty_per_session'));
        $this->assertTrue(ActivityLog::where('module', 'services')->where('description', 'like', '%Fat dissolving injection ×1%→%Fat dissolving injection ×9%')->exists());
    }

    public function test_child_line_prices_are_locked_without_price_override()
    {
        $receptionist = User::factory()->create(['role_id' => Role::where('name', 'Receptionist')->value('id')]);

        $form = Livewire::actingAs($receptionist)->test(Invoices::class)
            ->call('show_create_form')->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'service')->set('items.0.session', 1)->set('items.0.catalog_id', $this->hifu->id);
        $form->set('items.' . $this->indexOf($form, $this->syringe) . '.service_charges', 1)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(50, (float) $this->line(Invoice::first(), $this->syringe)->service_charges);
    }

    public function test_inventory_verify_is_clean_after_a_full_phase_3_cycle()
    {
        $invoice = $this->paidHifu();
        app(InvoiceService::class)->reduceStockUsed($this->line($invoice, $this->syringe), 4, 'Stopped early');
        $form = $this->edit($invoice);
        $form->set('items.' . $this->indexOf($form, $this->gloves) . '.quantity', 3)->call('generate_invoice');
        Livewire::test(Invoices::class)->call('prompt_delete', $invoice->id)->set('confirm_delete_password', 'password')->call('delete');

        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertSame([], $this->stock->verify());
    }
}
