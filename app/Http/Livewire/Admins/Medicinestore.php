<?php

namespace App\Http\Livewire\Admins;

use Livewire\Component;
use \App\Models\medicine;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class Medicinestore extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name;
    public $price;
    public $quantity;
    public $code;
    public $low_stock_threshold = 10;

    public $edit_medicine_id;
    public $button_text = "Add New Medicine";

    public $stock_in_medicine_id;
    public $stock_in_quantity;
    public $stock_in_cost;

    public function add_medicine()
    {
        if ($this->edit_medicine_id) {

            $this->update($this->edit_medicine_id);

        }else{
            abort_unless(auth()->user()->hasPermission('medicines_store', 'create'), 403);

            $this->validate([
                'name' => 'required',
                'price' => 'required|numeric',
                'quantity' => 'required|numeric|min:0',
                'code' => 'required',
                'low_stock_threshold' => 'required|numeric|min:0',
                ]);

            $medicine = medicine::create([
                'name'         => $this->name,
                'price'         => $this->price,
                'quantity'         => $this->quantity,
                'code'         => $this->code,
                'low_stock_threshold' => $this->low_stock_threshold,
            ]);

            if ($this->quantity > 0) {
                StockMovement::create([
                    'medicine_id' => $medicine->id,
                    'change' => $this->quantity,
                    'reason' => 'initial_stock',
                    'user_id' => auth()->id(),
                ]);
            }

            $this->name="";
            $this->price="";
            $this->quantity="";
            $this->code="";
            $this->low_stock_threshold = 10;

            session()->flash('message', 'Medicine Created successfully.');
        }

    }


     public function edit($id)
    {
        $Medicine = medicine::findOrFail($id);
        $this->edit_medicine_id = $id;

        $this->name = $Medicine->name;
        $this->price = $Medicine->price;
        $this->quantity = $Medicine->quantity;
        $this->code = $Medicine->code;
        $this->low_stock_threshold = $Medicine->low_stock_threshold;

        $this->button_text="Update Medicine";
    }

    public function update($id)
    {
        abort_unless(auth()->user()->hasPermission('medicines_store', 'update'), 403);

        $this->validate([
                'name' => 'required',
                'price' => 'required|numeric',
                'quantity' => 'required|numeric|min:0',
                'code' => 'required',
                'low_stock_threshold' => 'required|numeric|min:0',
            ]);

        $Medicine = medicine::findOrFail($id);
        $Medicine->name = $this->name;
        $Medicine->price = $this->price;
        $Medicine->quantity = $this->quantity;
        $Medicine->code = $this->code;
        $Medicine->low_stock_threshold = $this->low_stock_threshold;

        $Medicine->save();

        $this->name="";
        $this->price="";
        $this->quantity="";
        $this->code="";
        $this->low_stock_threshold = 10;

        $this->edit_medicine_id="";

        session()->flash('message', 'Medicine Updated Successfully.');

        $this->button_text = "Add New Medicine";

}

     public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('medicines_store', 'delete'), 403);

        medicine::findOrFail($id)->delete();
        session()->flash('message', 'Medicine Deleted Successfully.');

            $this->price="";
            $this->quantity="";
            $this->code="";
}

    public function show_stock_in_form($id)
    {
        $this->stock_in_medicine_id = $id;
        $this->stock_in_quantity = "";
        $this->stock_in_cost = "";
    }

    public function add_stock()
    {
        abort_unless(auth()->user()->hasPermission('medicines_store', 'update'), 403);

        $this->validate([
            'stock_in_quantity' => 'required|numeric|min:1',
            'stock_in_cost' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () {
            $medicine = medicine::findOrFail($this->stock_in_medicine_id);
            $medicine->increment('quantity', $this->stock_in_quantity);

            StockMovement::create([
                'medicine_id' => $medicine->id,
                'change' => $this->stock_in_quantity,
                'reason' => 'stock_in',
                'cost' => $this->stock_in_cost ?: null,
                'user_id' => auth()->id(),
            ]);
        });

        $this->stock_in_medicine_id = null;
        $this->stock_in_quantity = "";
        $this->stock_in_cost = "";

        session()->flash('message', 'Stock received and recorded successfully.');
    }

    public function render()
    {
        return view('livewire.admins.medicinestore',[
            'medicines' => medicine::latest()->paginate(10),
            'recentMovements' => StockMovement::with('medicine', 'user')->latest()->take(10)->get(),
        ])->layout('admins.layouts.app');
    }
}
