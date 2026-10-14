<?php

namespace Tests\Feature\Inventory;

use App\Http\Livewire\Admins\Inventory\Adjustments;
use App\Http\Livewire\Admins\Invoices;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemBatch;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\patient;
use App\Services\Inventory\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryPhase1Test extends TestCase
{
    use RefreshDatabase;

    private patient $patient;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->patient = patient::create(['name' => 'Ramsha', 'email' => 'r@example.com', 'phone' => '1', 'address' => '-', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+']);
        $this->item = InventoryItem::create(['code' => 'INJ-1', 'name' => 'Fat dissolving injection', 'cost_price' => 3000, 'sale_price' => 4500]);
    }

    private function openingStock(int $qty, string $batchNo = 'A'): ItemBatch
    {
        $adjustment = StockAdjustment::create(['adj_no' => DocumentSequence::next('ADJ'), 'adj_date' => now(), 'reason' => 'opening_stock']);

        return app(StockService::class)->stockIn($this->item, $qty, $batchNo, now()->addDays(90), 3000, $adjustment, null, 'adjustment_in');
    }

    private function productInvoiceForm(int $qty)
    {
        return Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'product')
            ->set('items.0.catalog_id', $this->item->id)
            ->set('items.0.quantity', $qty);
    }

    public function test_a_product_only_invoice_saves_without_a_doctor_and_deducts_stock()
    {
        $this->openingStock(10);

        $this->productInvoiceForm(3)->call('generate_invoice')->assertHasNoErrors()->assertSet('_page', 'view');

        $invoice = Invoice::with('items')->first();
        $this->assertNull($invoice->doctor_id);
        $this->assertEquals('product', $invoice->items[0]->line_type);
        $this->assertEquals($this->item->id, $invoice->items[0]->inventory_item_id);
        $this->assertEquals(13500, $invoice->grand_total);
        $this->assertEquals(7, $this->item->fresh()->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->item->id,
            'type' => 'invoice_out',
            'qty' => -3,
            'reference_id' => $invoice->id,
            'reference_line_id' => $invoice->items[0]->id,
        ]);
    }

    public function test_selling_more_than_available_shows_the_warning_and_save_anyway_goes_negative()
    {
        $this->openingStock(3);

        $component = $this->productInvoiceForm(5)->call('generate_invoice');

        $component->assertSet('_page', 'create')->assertDispatchedBrowserEvent('invoice-shortfall-open');
        $this->assertEquals(2, $component->get('shortfalls')[0]['short']);
        $this->assertEquals(0, Invoice::count());

        $component->call('generate_invoice', true)->assertSet('_page', 'view');

        $this->assertEquals(-2, $this->item->fresh()->qty_on_hand);
        $this->assertSame([], app(StockService::class)->verify());
    }

    public function test_editing_an_invoice_from_3_to_1_returns_2_to_the_same_batch()
    {
        $batch = $this->openingStock(10);
        $this->productInvoiceForm(3)->call('generate_invoice');
        $invoice = Invoice::first();
        $this->assertEquals(7, $batch->fresh()->qty_remaining);

        Livewire::test(Invoices::class)
            ->call('edit_invoice', $invoice->id)
            ->set('items.0.quantity', 1)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $this->assertEquals(9, $batch->fresh()->qty_remaining);
        $this->assertEquals(9, $this->item->fresh()->qty_on_hand);
        $this->assertSame([], app(StockService::class)->verify());
    }

    public function test_editing_does_not_warn_about_stock_the_invoice_already_holds()
    {
        $this->openingStock(3);
        $this->productInvoiceForm(3)->call('generate_invoice');

        Livewire::test(Invoices::class)
            ->call('edit_invoice', Invoice::first()->id)
            ->call('generate_invoice')
            ->assertSet('shortfalls', [])
            ->assertSet('_page', 'view');

        $this->assertEquals(0, $this->item->fresh()->qty_on_hand);
    }

    public function test_deleting_an_invoice_returns_all_its_stock_and_keeps_it_soft_deleted()
    {
        $batch = $this->openingStock(10);
        $this->productInvoiceForm(4)->call('generate_invoice');
        $invoice = Invoice::first();

        Livewire::test(Invoices::class)
            ->call('prompt_delete', $invoice->id)
            ->set('confirm_delete_password', 'password')
            ->call('delete');

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertEquals(10, $batch->fresh()->qty_remaining);
        $this->assertEquals(10, $this->item->fresh()->qty_on_hand);
        $this->assertDatabaseHas('stock_movements', ['reference_id' => $invoice->id, 'type' => 'invoice_reversal', 'qty' => 4]);
    }

    public function test_without_price_override_the_product_price_is_forced_to_the_sale_price()
    {
        $this->openingStock(10);
        $receptionist = User::factory()->create(['role_id' => Role::where('name', 'Receptionist')->value('id')]);
        $this->assertFalse($receptionist->hasPermission('inventory', 'price_override'));

        Livewire::actingAs($receptionist)->test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $this->patient->id)
            ->set('items.0.type', 'product')
            ->set('items.0.catalog_id', $this->item->id)
            ->set('items.0.quantity', 1)
            ->set('items.0.service_charges', 1)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $this->assertEquals(4500, Invoice::with('items')->first()->items[0]->service_charges);
    }

    public function test_custom_lines_have_no_stock_effect()
    {
        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $this->patient->id)
            ->set('items.0.service', 'Carbon+PRP+Meso')
            ->set('items.0.service_charges', 25000)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $this->assertEquals('custom', Invoice::with('items')->first()->items[0]->line_type);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_opening_stock_adjustment_creates_a_batch_and_updates_cost()
    {
        Livewire::test(Adjustments::class)
            ->call('show_create_form')
            ->set('lines.0.item_id', $this->item->id)
            ->set('lines.0.qty', 12)
            ->set('lines.0.batch_no', 'LOT-9')
            ->set('lines.0.expiry_date', now()->addYear()->toDateString())
            ->set('lines.0.unit_cost', 3200)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('_page', 'view');

        $adjustment = StockAdjustment::with('items')->first();
        $this->assertStringStartsWith('ADJ-', $adjustment->adj_no);
        $this->assertEquals(12, $this->item->fresh()->qty_on_hand);
        $this->assertEquals(3200, $this->item->fresh()->cost_price);
        $this->assertNotNull($adjustment->items[0]->batch_id);
        $this->assertEquals('adjustment', ItemBatch::first()->source);
    }

    public function test_deleting_an_adjustment_whose_stock_was_sold_is_refused()
    {
        Livewire::test(Adjustments::class)
            ->call('show_create_form')
            ->set('lines.0.item_id', $this->item->id)
            ->set('lines.0.qty', 5)
            ->call('save');
        $this->productInvoiceForm(2)->call('generate_invoice');

        Livewire::test(Adjustments::class)->call('delete', StockAdjustment::first()->id);

        $this->assertNotSoftDeleted('stock_adjustments', ['id' => StockAdjustment::first()->id]);
        $this->assertEquals(3, $this->item->fresh()->qty_on_hand);
    }

    public function test_a_user_without_view_cost_sees_no_cost_on_the_items_screen()
    {
        $this->item->update(['cost_price' => 3123.45]);
        $nurse = User::factory()->create(['role_id' => Role::where('name', 'Nurse')->value('id')]);

        $this->actingAs($nurse)->get(route('admin_inventory_items'))
            ->assertOk()
            ->assertSee('Fat dissolving injection')
            ->assertDontSee('3,123.45');

        $this->actingAs(User::factory()->create())->get(route('admin_inventory_items'))
            ->assertSee('3,123.45');
    }

    public function test_the_old_medicines_store_route_is_gone()
    {
        $this->get('/admin/medicinesStore')->assertNotFound();
    }
}
