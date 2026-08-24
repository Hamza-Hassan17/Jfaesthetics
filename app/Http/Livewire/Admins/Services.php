<?php

namespace App\Http\Livewire\Admins;

use App\Models\Service;
use Livewire\Component;
use Livewire\WithPagination;

class Services extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $name;
    public $price;

    public $edit_service_id;
    public $button_text = "Add New Service";

    public function add_service()
    {
        if ($this->edit_service_id) {
            $this->update($this->edit_service_id);
        } else {
            $this->validate([
                'name' => 'required',
                'price' => 'required|numeric|min:0',
            ]);

            Service::create([
                'name' => $this->name,
                'price' => $this->price,
            ]);

            $this->name = "";
            $this->price = "";

            session()->flash('message', 'Service Created successfully.');
        }
    }

    public function edit($id)
    {
        $service = Service::findOrFail($id);
        $this->edit_service_id = $id;
        $this->name = $service->name;
        $this->price = $service->price;
        $this->button_text = "Update Service";
    }

    public function update($id)
    {
        $this->validate([
            'name' => 'required',
            'price' => 'required|numeric|min:0',
        ]);

        $service = Service::findOrFail($id);
        $service->name = $this->name;
        $service->price = $this->price;
        $service->save();

        $this->name = "";
        $this->price = "";
        $this->edit_service_id = "";

        session()->flash('message', 'Service Updated Successfully.');
        $this->button_text = "Add New Service";
    }

    public function delete($id)
    {
        Service::findOrFail($id)->delete();
        session()->flash('message', 'Service Deleted Successfully.');
    }

    public function render()
    {
        return view('livewire.admins.services', [
            'services' => Service::latest()->paginate(10),
        ])->layout('admins.layouts.app');
    }
}
