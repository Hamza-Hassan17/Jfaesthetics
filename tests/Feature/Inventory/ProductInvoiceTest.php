<?php

namespace Tests\Feature\Inventory;

use App\Http\Livewire\Admins\Inventory\ProductInvoices;
use App\Http\Livewire\Admins\Invoices;
use App\Http\Livewire\Admins\Services;
use App\Models\ActivityLog;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemBatch;
use App\Models\ProductInvoice;
use App\Models\ProductInvoiceItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ShortfallSettlement;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\ProductInvoiceService;
use App\Services\Inventory\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private patient $patient;
    private Service $hifu;
    private InventoryItem $injection;
    private InventoryItem $syringe;
    private InventoryItem $gloves;
    private InventoryItem $sunblock;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->actingAs(User::factory()->create());
        $this->stock = app(StockService::class);

        $this->patient = patient::create(['name' => 'Ramsha', 'email' => 'ramsha@example.com', 'phone' => '0300-1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+']);
        $this->injection = InventoryItem::create(['code' => 'FDI', 'name' => 'Fat dissolving injection', 'cost_price' => 3000, 'sale_price' => 4500]);
        $this->syringe = InventoryItem::create(['code' => 'SYR', 'name' => 'Syringe 3ml', 'cost_price' => 20, 'sale_price' => 50]);
        $this->gloves = InventoryItem::create(['code' => 'GLV', 'name' => 'Gloves (pair)', 'cost_price' => 15, 'sale_price' => 0, 'default_billable' => false]);
        $this->sunblock = InventoryItem::create(['code' => 'SUN', 'name' => 'Sunblock SPF 50', 'cost_price' => 2000, 'sale_price' => 3200]);

        $this->hifu = Service::create(['name' => 'Hifu+MFU+RF+ Fat dissolving Inj', 'price' => 135000]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->injection->id, 'qty_per_session' => 1, 'is_billable' => true]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->syringe->id, 'qty_per_session' => 2, 'is_billable' => true]);
        ServiceItem::create(['service_id' => $this->hifu->id, 'item_id' => $this->gloves->id, 'qty_per_session' => 1, 'is_billable' => false]);

        foreach ([[$this->injection, 20, 3000], [$this->syringe, 100, 20], [$this->gloves, 100, 15], [$this->sunblock, 10, 2000]] as [$item, $qty, $cost]) {
            $this->receive($item, $qty, $item->code . '-A', now()->addYear(), $cost);
        }
    }

    private function receive(InventoryItem $item, int $qty, string $batch, $expiry, float $cost): ItemBatch
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);

        return $this->stock->stockIn($item, $qty, $batch, $expiry, $cost, $adjustment, null, 'adjustment_in');
    }

    private function hifuInvoice(int $sessions = 3, float $paid = 0): Invoice
    {
        Livewire::test(Invoices::class)->call('show_create_form')
            ->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'service')->set('items.0.session', $sessions)->set('items.0.catalog_id', $this->hifu->id)
            ->set('payments.0.amount', $paid)
            ->call('generate_invoice')->assertHasNoErrors();

        return Invoice::latest('id')->first();
    }

    private function recipePi(Invoice $invoice): ProductInvoice
    {
        return ProductInvoice::where('invoice_id', $invoice->id)->where('source', 'recipe')->with('items')->firstOrFail();
    }

    private function line(ProductInvoice $pi, InventoryItem $item): ProductInvoiceItem
    {
        return $pi->fresh('items')->items->firstWhere('item_id', $item->id);
    }

    private function productForm(array $lines, ?int $invoiceId = null)
    {
        $form = Livewire::test(ProductInvoices::class)->call('show_create_form');
        $invoiceId ? $form->set('invoice_id', $invoiceId) : $form->set('link_mode', 'standalone');
        foreach ($lines as $i => [$item, $qty]) {
            if ($i > 0) {
                $form->call('add_line');
            }
            $form->set("lines.$i.item_id", $item->id)->set("lines.$i.quantity", $qty);
        }

        return $form;
    }

    // ---- Separate document, numbering, linking ----

    public function test_a_product_invoice_gets_its_own_pi_number_and_is_separate_from_patient_invoices()
    {
        $this->productForm([[$this->sunblock, 2]])->call('save')->assertHasNoErrors()->assertSet('_page', 'view');

        $pi = ProductInvoice::first();
        $this->assertMatchesRegularExpression('/^PI-\d{7}$/', $pi->pi_no);
        $this->assertEquals(0, Invoice::count());
        $this->assertEquals(8, $this->sunblock->fresh()->qty_on_hand);
        $this->assertEquals(6400, $pi->grand_total);
    }

    public function test_linking_to_a_patient_invoice_takes_the_patient_from_it_and_keeps_its_own_total_and_payments()
    {
        $invoice = $this->hifuInvoice(1, 135000);

        $form = $this->productForm([[$this->sunblock, 1]], $invoice->id);
        $form->set('payments.0.amount', 1000)->call('save')->assertHasNoErrors();

        $pi = ProductInvoice::where('source', 'manual')->first();
        $this->assertEquals($invoice->id, $pi->invoice_id);
        $this->assertEquals('Ramsha', $pi->buyer_name);
        $this->assertEquals([3200, 1000, 2200], [$pi->grand_total, $pi->paid_total, $pi->unpaid_total]);
        $this->assertEquals(135000, $invoice->fresh(['items', 'payments'])->grand_total, 'The patient invoice total is unchanged.');
        $this->assertEquals(0, $invoice->fresh(['items', 'payments'])->unpaid_total);
    }

    public function test_without_a_patient_invoice_a_walk_in_buyer_is_allowed()
    {
        $this->productForm([[$this->sunblock, 1]])->set('walkin_name', 'Counter sale')->set('walkin_phone', '0300-9')->call('save')->assertHasNoErrors();

        $pi = ProductInvoice::first();
        $this->assertNull($pi->invoice_id);
        $this->assertEquals(['Counter sale', '0300-9'], [$pi->buyer_name, $pi->buyer_phone]);
    }

    public function test_linked_mode_requires_choosing_an_invoice()
    {
        Livewire::test(ProductInvoices::class)->call('show_create_form')
            ->set('lines.0.item_id', $this->sunblock->id)
            ->call('save')->assertHasErrors('invoice_id');
    }

    public function test_the_product_option_is_gone_from_new_patient_invoice_lines()
    {
        Livewire::test(Invoices::class)->call('show_create_form')->assertDontSee('<option value="product">Product</option>', false);
    }

    // ---- Recipe consumables move to a linked product invoice ----

    public function test_a_service_with_a_recipe_creates_a_linked_product_invoice_with_qty_times_sessions()
    {
        $invoice = $this->hifuInvoice(3);
        $pi = $this->recipePi($invoice);

        $this->assertEquals(1, $invoice->fresh('items')->items->count(), 'Only the service line is on the patient invoice.');
        $this->assertEquals([3, 6, 3], [$this->line($pi, $this->injection)->quantity, $this->line($pi, $this->syringe)->quantity, $this->line($pi, $this->gloves)->quantity]);
        $this->assertEquals(94, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(97, $this->gloves->fresh()->qty_on_hand);
        $this->assertEquals(3 * 4500 + 6 * 50, $pi->grand_total, 'Non-billable gloves add nothing.');
    }

    public function test_changing_sessions_recalculates_consumables_but_a_hand_edited_line_keeps_its_qty()
    {
        $invoice = $this->hifuInvoice(3);
        $pi = $this->recipePi($invoice);

        $form = Livewire::test(ProductInvoices::class)->call('edit', $pi->id);
        $index = collect($form->get('lines'))->search(fn ($l) => (int) $l['item_id'] === $this->injection->id);
        $form->set("lines.$index.quantity", 4)->call('save')->assertHasNoErrors();

        Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id)->set('items.0.session', 5)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(10, $this->line($pi, $this->syringe)->quantity);
        $this->assertEquals(90, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(4, $this->line($pi, $this->injection)->quantity);
    }

    public function test_changing_a_recipe_after_billing_leaves_existing_product_invoices_unchanged()
    {
        $invoice = $this->hifuInvoice(3);
        ServiceItem::where('item_id', $this->syringe->id)->update(['qty_per_session' => 5]);

        Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id)->set('items.0.session', 4)->call('generate_invoice');

        $this->assertEquals(8, $this->line($this->recipePi($invoice), $this->syringe)->quantity, 'Uses the copied 2 per session, not the new 5.');
    }

    public function test_removing_the_service_line_returns_its_consumables()
    {
        $invoice = $this->hifuInvoice(3);
        $form = Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id);
        $form->call('add_item')->set('items.1.service', 'Consultation')->set('items.1.service_charges', 2000)->call('remove_item', 0)->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(0, ProductInvoice::where('invoice_id', $invoice->id)->count(), 'An empty recipe invoice is removed.');
    }

    public function test_deleting_the_patient_invoice_cancels_its_product_invoices_and_returns_stock()
    {
        $invoice = $this->hifuInvoice(3);
        $this->productForm([[$this->sunblock, 2]], $invoice->id)->call('save');

        Livewire::test(Invoices::class)->call('prompt_delete', $invoice->id)->set('confirm_delete_password', 'password')->call('delete');

        $this->assertEquals(0, ProductInvoice::count());
        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
        $this->assertEquals(10, $this->sunblock->fresh()->qty_on_hand);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_editing_a_pre_feature_invoice_does_not_create_consumables()
    {
        $invoice = Invoice::create(['invoice_number' => 'OLD-1', 'patient_id' => $this->patient->id]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'line_type' => 'service', 'service_id' => $this->hifu->id, 'service' => $this->hifu->name,
            'quantity' => 1, 'session_number' => 3, 'service_charges' => 135000, 'discount_type' => 'flat', 'discount_value' => 0,
            'sub_total' => 135000, 'discount' => 0, 'after_discount' => 135000,
        ]);

        Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id)->set('notes', 'Re-saved')->call('generate_invoice')->assertHasNoErrors();

        $this->assertEquals(0, ProductInvoice::count());
        $this->assertEquals(100, $this->syringe->fresh()->qty_on_hand);
    }

    // ---- Stock rules on product invoices ----

    public function test_editing_qty_down_returns_stock_to_the_same_batch_and_up_takes_only_the_extra()
    {
        $this->productForm([[$this->injection, 3]])->call('save');
        $pi = ProductInvoice::first();
        $original = ItemBatch::where('batch_no', 'FDI-A')->first();
        $earlier = $this->receive($this->injection, 5, 'FDI-EARLY', now()->addMonth(), 2800);

        Livewire::test(ProductInvoices::class)->call('edit', $pi->id)->set('lines.0.quantity', 1)->call('save');
        $this->assertEquals([19, 5], [$original->fresh()->qty_remaining, $earlier->fresh()->qty_remaining]);

        Livewire::test(ProductInvoices::class)->call('edit', $pi->id)->set('lines.0.quantity', 2)->call('save');
        $this->assertEquals([19, 4], [$original->fresh()->qty_remaining, $earlier->fresh()->qty_remaining]);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_short_stock_warns_then_goes_negative_and_a_later_receipt_settles_and_recosts()
    {
        $form = $this->productForm([[$this->sunblock, 12]]);
        $form->call('save')->assertDispatchedBrowserEvent('pi-shortfall-open');
        $this->assertEquals(0, ProductInvoice::count());
        $form->call('save', true)->assertSet('_page', 'view');
        $this->assertEquals(-2, $this->sunblock->fresh()->qty_on_hand);

        $line = ProductInvoice::first()->items->first();
        $settling = $this->receive($this->sunblock, 10, 'SUN-B', now()->addYear(), 2300);

        $this->assertEquals(8, $this->sunblock->fresh()->qty_on_hand);
        $this->assertEquals(8, $settling->fresh()->qty_remaining);
        $this->assertEquals(2, ShortfallSettlement::first()->qty);
        $this->assertEquals(round((10 * 2000 + 2 * 2300) / 12, 2), (float) $line->fresh()->unit_cost);
        $this->assertSame([], $this->stock->verify());
    }

    public function test_a_paid_product_invoice_cannot_drop_below_paid_but_stock_only_reduction_works()
    {
        $invoice = $this->hifuInvoice(5);
        $pi = $this->recipePi($invoice);
        app(ProductInvoiceService::class)->addPayment($pi, now()->toDateString(), $pi->grand_total, 'Cash');

        $form = Livewire::test(ProductInvoices::class)->call('edit', $pi->id);
        $index = collect($form->get('lines'))->search(fn ($l) => (int) $l['item_id'] === $this->injection->id);
        $form->set("lines.$index.quantity", 2)->call('save')->assertHasErrors('lines');

        $line = $this->line($pi, $this->injection);
        Livewire::test(ProductInvoices::class)->call('view', $pi->id)
            ->call('prompt_reduce', $line->id)->set('reduce_qty', 3)->set('reduce_reason', 'Patient stopped after 2 of 5 sessions')
            ->call('reduce_stock')->assertHasNoErrors();

        $this->assertEquals([5, 2], [$line->fresh()->quantity, $line->fresh()->stock_qty]);
        $this->assertEquals(18, $this->injection->fresh()->qty_on_hand);
        $this->assertTrue(ActivityLog::where('description', 'like', '%stock-only reduction%used 5 → 2%')->exists());

        // A later sessions sync keeps the reduction (billed qty unchanged).
        Livewire::test(Invoices::class)->call('edit_invoice', $invoice->id)->set('notes', 'x')->call('generate_invoice');
        $this->assertEquals(2, $line->fresh()->stock_qty);
    }

    public function test_cancelling_a_product_invoice_returns_its_stock()
    {
        $this->productForm([[$this->sunblock, 3]])->call('save');
        $pi = ProductInvoice::first();

        Livewire::test(ProductInvoices::class)->call('view', $pi->id)->set('cancel_password', 'password')->call('cancel_pi');

        $this->assertSoftDeleted('product_invoices', ['id' => $pi->id]);
        $this->assertEquals(10, $this->sunblock->fresh()->qty_on_hand);
    }

    public function test_prices_are_locked_without_price_override()
    {
        $receptionist = User::factory()->create(['role_id' => Role::where('name', 'Receptionist')->value('id')]);

        Livewire::actingAs($receptionist)->test(ProductInvoices::class)->call('show_create_form')->set('link_mode', 'standalone')
            ->set('lines.0.item_id', $this->sunblock->id)->set('lines.0.unit_price', 1)->call('save')->assertHasNoErrors();

        $this->assertEquals(3200, (float) ProductInvoice::first()->items->first()->unit_price);
    }

    // ---- PDF, dashboard, report ----

    public function test_pdf_prints_products_only_or_combined_with_the_patient_invoice_and_hides_non_billable_lines()
    {
        $invoice = $this->hifuInvoice(1);
        $pi = $this->recipePi($invoice);

        $this->get(route('admin_inventory_product_invoice_print', $pi->id))->assertOk()
            ->assertSee($pi->pi_no)->assertSee('Syringe 3ml')->assertDontSee('Gloves (pair)')->assertDontSee('Services —');

        $this->get(route('admin_inventory_product_invoice_print', ['productInvoice' => $pi->id, 'combined' => 1]))->assertOk()
            ->assertSee('Services — invoice #' . $invoice->invoice_number)->assertSee($this->hifu->name)->assertSee('Grand total');
    }

    public function test_dashboard_card_and_report_tab_show_product_sales_separately()
    {
        $this->productForm([[$this->sunblock, 2]])->set('payments.0.amount', 2000)->call('save');

        Livewire::test(\App\Http\Livewire\Admins\Dashboard::class)->assertViewHas('productSales', fn ($s) => $s['total'] == 6400 && $s['collected'] == 2000 && $s['unpaid'] == 4400);
        $this->get(route('admin_reports_product_invoices'))->assertOk()->assertSee(ProductInvoice::first()->pi_no);
        $this->get(route('admin_reports_product_invoices_print'))->assertOk()->assertSee('Product Invoices Report');
    }

    public function test_recipes_still_need_manage_recipes()
    {
        $receptionist = User::factory()->create(['role_id' => Role::where('name', 'Receptionist')->value('id')]);

        Livewire::actingAs($receptionist)->test(Services::class)->call('show_recipe', $this->hifu->id)->call('save_recipe')->assertForbidden();
    }
}
