<?php

namespace App\Http\Livewire\Admins;

use App\Models\patient;
use App\Traits\LogsActivity;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Patients extends Component
{
    use WithFileUploads;
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $name;
    public $email;
    public $phone;
    public $gender;
    public $age;
    public $address;
    public $bloodgroup;
    public $photo;
    public $edit_photo;
    public $edit_patient_id;
    public $button_text = "Add New Patient";

    // Re-authentication before a destructive action, per RBAC spec 8.6.
    public $confirm_delete_id;
    public $confirm_delete_password;

    public $_page;
    public function mount()
    {
        $this->_page = 'index';
    }

    public function show_create_form()
    {
        $this->_page = "create";
    }

    public function show_edit_form($id)
    {
        $this->_page = "edit";
        $patient = patient::findOrFail($id);
        $this->edit_patient_id = $id;

        $this->name = $patient->name;
        $this->email = $patient->email;
        $this->address = $patient->address;
        $this->phone = $patient->phone;
        $this->gender = $patient->gender;
        $this->age = $patient->age;
        $this->bloodgroup = $patient->bloodgroup;
        $this->edit_photo = $patient->photo_path;
    }

    public function show_index()
    {
        $this->_page = "index";
    }

    public function add_patient()
    {
        if ($this->edit_patient_id) {

            $this->update($this->edit_patient_id);

        } else {
            abort_unless(auth()->user()->hasPermission('patients', 'create'), 403);

            $this->validate([
                'name' => 'required',
                'email' => 'nullable|email',
                'address' => 'required',
                'phone' => 'required|numeric',
                'gender' => 'required',
                'age' => 'required',
                'bloodgroup' => 'nullable',
                'photo' => 'nullable|max:3072',
            ]);

            $newPatient = patient::create([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'gender' => $this->gender,
                'address' => $this->address,
                'age' => $this->age,
                'bloodgroup' => $this->bloodgroup,
                'photo_path' => $this->storeImage(),
            ]);
            $this->logActivity('created', 'patients', $newPatient->id, "Created patient '{$newPatient->name}'.");
            //unset variables
            $this->name = "";
            $this->email = "";
            $this->password = "";
            $this->address = "";
            $this->phone = "";
            $this->bloodgroup = "";
            $this->address = "";
            $this->age = "";
            $this->photo = "";

            session()->flash('message', 'Patient Created successfully.');
            $this->_page = "index";
        }

    }

    public function storeImage()
    {
        if (!$this->photo) {
            return null;
        }
        $imag = ImageManagerStatic::make($this->photo)->encode('jpg');
        $name = Str::random() . '.jpg';
        Storage::disk('public')->put($name, $imag);
        return $name;
    }


    public function update($id)
    {
        abort_unless(auth()->user()->hasPermission('patients', 'update'), 403);

        $this->validate([
            'name' => 'required',
            'email' => 'nullable|email',
            'address' => 'required',
            'phone' => 'required|numeric',
            'gender' => 'required',
            'age' => 'required',
            'bloodgroup' => 'nullable',
            'photo' => 'nullable|max:3072',
        ]);

        $patient = patient::findOrFail($id);
        $patient->name = $this->name;
        $patient->email = $this->email;
        $patient->address = $this->address;
        $patient->age = $this->age;
        $patient->bloodgroup = $this->bloodgroup;
        $patient->phone = $this->phone;

        if ($this->photo) {
            Storage::disk('public')->delete($patient->photo_path);
            $patient->photo_path = $this->storeImage();

        }

        $patient->save();
        $this->logActivity('updated', 'patients', $patient->id, "Updated patient '{$patient->name}'.");

        $this->name = "";
        $this->email = "";
        $this->address = "";
        $this->phone = "";
        $this->bloodgroup = "";
        $this->address = "";
        $this->age = "";
        $this->photo = "";
        $this->edit_photo = "";
        $this->edit_patient_id = "";

        session()->flash('message', 'Patient Updated Successfully.');

        $this->button_text = "Add New Patient";
        $this->_page = "index";
    }

    public function prompt_delete($id)
    {
        abort_unless(auth()->user()->hasPermission('patients', 'delete'), 403);
        $this->confirm_delete_id = $id;
        $this->confirm_delete_password = '';
        $this->resetErrorBag('confirm_delete_password');
    }

    /**
     * Deleting a patient record is a high-risk action (RBAC spec 8.6) —
     * require the acting user to re-enter their own password immediately
     * before it happens, not just click through a JS confirm() dialog.
     */
    public function delete()
    {
        abort_unless(auth()->user()->hasPermission('patients', 'delete'), 403);

        if (!\Illuminate\Support\Facades\Hash::check($this->confirm_delete_password ?? '', auth()->user()->password)) {
            $this->addError('confirm_delete_password', 'Incorrect password.');
            return;
        }

        $patient = patient::find($this->confirm_delete_id);
        if (!$patient) {
            $this->confirm_delete_id = null;
            return;
        }

        $name = $patient->name;
        $id = $patient->id;
        Storage::disk('public')->delete($patient->photo_path);
        $patient->delete();
        $this->logActivity('deleted', 'patients', $id, "Deleted patient '{$name}'.");

        $this->confirm_delete_id = null;
        $this->confirm_delete_password = '';
        $this->dispatchBrowserEvent('patient-delete-confirmed');
        session()->flash('message', 'Patient Deleted Successfully.');
    }

    public function render()
    {
        if ($this->_page == "index") {
            return view('livewire.admins.patients.index', [
                'patients' => patient::latest()->paginate(10),
            ])->layout('admins.layouts.app');
        } else if ($this->_page == "create") {
            return view('livewire.admins.patients.create');
        } else if ($this->_page == "edit") {
            return view('livewire.admins.patients.edit');
        }
    }
}
