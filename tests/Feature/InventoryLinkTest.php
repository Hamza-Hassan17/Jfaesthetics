<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Invoices;
use App\Models\medicine;
use App\Models\patient;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryLinkTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): patient
    {
        return patient::create([
            'name' => 'Test Patient', 'email' => 'p@example.com', 'phone' => '123',
            'address' => 'Karachi', 'gender' => 'Female', 'age' => 30, 'bloodgroup' => 'O+',
        ]);
    }

    /** @test */
    public function adding_a_medicine_line_item_deducts_stock_and_logs_the_movement()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();
        $med = medicine::create([
            'name' => 'Botox 100u', 'price' => 5000, 'quantity' => 20, 'code' => 'BTX-100',
            'low_stock_threshold' => 5,
        ]);

        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('items.0.type', 'medicine')
            ->set('items.0.catalog_id', $med->id)
            ->set('items.0.quantity', 3)
            ->set('items.0.service_charges', 5000)
            ->call('generate_invoice')
            ->assertHasNoErrors();

        $this->assertEquals(17, $med->fresh()->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'medicine_id' => $med->id,
            'change' => -3,
            'reason' => 'invoice_deduction',
        ]);
    }

    /** @test */
    public function an_invoice_cannot_be_saved_for_more_stock_than_is_available()
    {
        $this->actingAs(User::factory()->create());
        $patient = $this->patient();
        $med = medicine::create([
            'name' => 'Botox 100u', 'price' => 5000, 'quantity' => 2, 'code' => 'BTX-100',
            'low_stock_threshold' => 5,
        ]);

        Livewire::test(Invoices::class)
            ->call('show_create_form')
            ->set('patient_id', $patient->id)
            ->set('items.0.type', 'medicine')
            ->set('items.0.catalog_id', $med->id)
            ->set('items.0.quantity', 10)
            ->set('items.0.service_charges', 5000)
            ->call('generate_invoice')
            ->assertHasErrors(['items.0.quantity']);

        // Stock must be untouched, and no invoice/movement should exist.
        $this->assertEquals(2, $med->fresh()->quantity);
        $this->assertEquals(0, \App\Models\Invoice::count());
        $this->assertEquals(0, StockMovement::count());
    }

    /** @test */
    public function a_medicine_is_flagged_low_stock_once_it_reaches_its_threshold()
    {
        $med = medicine::create([
            'name' => 'Botox 100u', 'price' => 5000, 'quantity' => 6, 'code' => 'BTX-100',
            'low_stock_threshold' => 5,
        ]);

        $this->assertFalse($med->is_low_stock);

        $med->decrement('quantity', 1);

        $this->assertTrue($med->fresh()->is_low_stock);
    }
}
