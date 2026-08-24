<?php

namespace App\Http\Livewire\Admins;

use App\Models\birthreport as ModelsBirthreport;
use App\Models\doctor;
use App\Models\patient;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Birthreport extends Component
{

    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $patient;
    public $details;
    public $doctor;
    public $gender;

    public $edit_birth_report_id;
    public $button_text = "Add New Birth Report";



    public function add_birthreport()
    {
        if ($this->edit_birth_report_id) {

            $this->update($this->edit_birth_report_id);

        }else{
            $this->validate([
                'patient' => 'required',
                'doctor' => 'required',
                'details' => 'required',
                'gender' => 'required',
                ]);

            ModelsBirthreport::create([
                'patient_id'          => $this->patient,
                'description'         => $this->details,
                'doctor_id'         => $this->doctor,
                'gender'         => $this->gender,
            ]);

            $this->patient="";
            $this->details="";
            $this->doctor="";
            $this->gender="";

            session()->flash('message', 'Birth Report Created successfully.');
        }

    }


     public function edit($id)
    {
        $birthreport = ModelsBirthreport::findOrFail($id);
        $this->edit_birth_report_id = $id;
        $this->patient = $birthreport->patient_id;
        $this->details = $birthreport->description;
        $this->doctor = $birthreport->doctor_id;
        $this->gender = $birthreport->gender;

        $this->button_text="Update Birth Report";
    }

    public function update($id)
    {
        $this->validate([
                'patient' => 'required',
                'details' => 'required',
                'doctor' => 'required',
                'gender' => 'required',
            ]);

        $birthreport = ModelsBirthreport::findOrFail($id);
        $birthreport->patient_id = $this->patient;
        $birthreport->description = $this->details;
        $birthreport->doctor_id = $this->doctor;
        $birthreport->gender = $this->gender;

        $birthreport->save();

        $this->patient="";
        $this->details="";
        $this->doctor="";
        $this->gender="";
        $this->edit_birth_report_id="";

        session()->flash('message', 'Birth Report Updated Successfully.');

        $this->button_text = "Add New Birth Report";

}

     public function delete($id)
    {
        ModelsBirthreport::findOrFail($id)->delete();
        session()->flash('message', 'Birthreport Deleted Successfully.');
    }

    public function render()
    {
        return view('livewire.admins.birthreport',[
            'BirthReports' => ModelsBirthreport::latest()->paginate(10),
            'doctors' => doctor::all(),
            'patients' => patient::all(),
        ])->layout('admins.layouts.app');
    }
}
