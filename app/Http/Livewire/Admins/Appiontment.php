<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\nurse;
use App\Models\patient;
use App\Traits\LogsActivity;
use Livewire\Component;
use Livewire\WithPagination;

class Appiontment extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';

    public $editing_id;
    public $view_id;

    public $patient_id;
    public $case_no;
    public $doctor_id;
    public $nurse_id;
    public $age;
    public $location;
    public $intime;
    public $outtime;
    public $description;
    public $prescription;

    public $quick_patient_name;
    public $quick_patient_phone;
    public $quick_patient_address;
    public $quick_patient_gender;
    public $quick_patient_age;
    public $quick_patient_bloodgroup;

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->patient_id = '';
        $this->case_no = '';
        $this->doctor_id = '';
        $this->nurse_id = '';
        $this->age = '';
        $this->location = '';
        $this->intime = now()->format('Y-m-d\TH:i');
        $this->outtime = '';
        $this->description = '';
        $this->prescription = '';
    }

    public function show_create_form()
    {
        $this->resetForm();
        $this->_page = 'create';
    }

    public function show_index()
    {
        $this->_page = 'index';
    }

    public function view($id)
    {
        $this->assertOwnsAppointment(appointment::findOrFail($id));
        $this->view_id = $id;
        $this->_page = 'view';
    }

    public function updated($name, $value)
    {
        if ($name === 'patient_id' && $value !== '' && !$this->age) {
            $patient = patient::find($value);
            if ($patient && $patient->age) {
                $this->age = $patient->age;
            }
        }
    }

    public function edit($id)
    {
        $appointment = appointment::findOrFail($id);
        $this->assertOwnsAppointment($appointment);
        $this->editing_id = $id;

        $this->patient_id = $appointment->patient_id;
        $this->case_no = $appointment->case_no;
        $this->doctor_id = $appointment->doctor_id;
        $this->nurse_id = $appointment->nurse_id;
        $this->age = $appointment->age;
        $this->location = $appointment->location;
        $this->intime = optional($appointment->intime)->format('Y-m-d\TH:i');
        $this->outtime = optional($appointment->outtime)->format('Y-m-d\TH:i');
        $this->description = $appointment->description;
        $this->prescription = $appointment->prescription;

        $this->_page = 'create';
    }

    public function add_quick_patient()
    {
        $this->validate([
            'quick_patient_name' => 'required|string|min:2|max:50',
            'quick_patient_phone' => 'required|numeric',
            'quick_patient_address' => 'nullable|string',
            'quick_patient_gender' => 'nullable|string',
            'quick_patient_age' => 'nullable|numeric',
            'quick_patient_bloodgroup' => 'nullable|string',
        ]);

        $newPatient = patient::create([
            'name' => $this->quick_patient_name,
            'phone' => $this->quick_patient_phone,
            'address' => $this->quick_patient_address,
            'gender' => $this->quick_patient_gender,
            'age' => $this->quick_patient_age,
            'bloodgroup' => $this->quick_patient_bloodgroup,
        ]);

        $this->patient_id = $newPatient->id;
        if ($this->quick_patient_age) {
            $this->age = $this->quick_patient_age;
        }

        $this->quick_patient_name = '';
        $this->quick_patient_phone = '';
        $this->quick_patient_address = '';
        $this->quick_patient_gender = '';
        $this->quick_patient_age = '';
        $this->quick_patient_bloodgroup = '';

        $this->dispatchBrowserEvent('patient-quick-added');
        session()->flash('message', 'Patient added and selected for this appointment.');
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('appointments', $this->editing_id ? 'update' : 'create'), 403);

        $user = auth()->user();
        if ($user && $user->doctor_id) {
            // A doctor account can only ever book appointments under their
            // own name, regardless of what the (hidden) doctor field holds.
            $this->doctor_id = $user->doctor_id;
        }

        $this->validate([
            'patient_id' => 'required|numeric',
            'case_no' => 'nullable|string',
            'doctor_id' => 'required|numeric',
            'nurse_id' => 'nullable|numeric',
            'age' => 'nullable|numeric',
            'location' => 'nullable|string',
            'intime' => 'required|date',
            'outtime' => 'nullable|date',
            'description' => 'required|string',
            'prescription' => 'nullable|string',
        ]);

        $data = [
            'patient_id' => $this->patient_id,
            'case_no' => $this->case_no ?: null,
            'doctor_id' => $this->doctor_id,
            'nurse_id' => $this->nurse_id ?: null,
            'age' => $this->age ?: null,
            'location' => $this->location ?: null,
            'intime' => $this->intime,
            'outtime' => $this->outtime ?: null,
            'description' => $this->description,
            'prescription' => $this->prescription,
        ];

        if ($this->editing_id) {
            $existing = appointment::findOrFail($this->editing_id);
            $this->assertOwnsAppointment($existing);
            $existing->update($data);
            $savedId = $this->editing_id;
            $this->logActivity('updated', 'appointments', $savedId, "Updated appointment for patient #{$this->patient_id}.");
            session()->flash('message', 'Appointment updated successfully.');
        } else {
            $savedId = appointment::create($data)->id;
            $this->logActivity('created', 'appointments', $savedId, "Created appointment for patient #{$this->patient_id}.");
            session()->flash('message', 'Appointment created successfully.');
        }

        $this->view_id = $savedId;
        $this->_page = 'view';
    }

    public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('appointments', 'delete'), 403);

        $appointment = appointment::findOrFail($id);
        $this->assertOwnsAppointment($appointment);
        $appointment->delete();
        $this->logActivity('deleted', 'appointments', $id, "Deleted appointment #{$id}.");
        session()->flash('message', 'Appointment deleted successfully.');
    }

    public function render()
    {
        if ($this->_page === 'view' && $this->view_id) {
            return view('livewire.admins.appiontment', [
                'appointment' => appointment::with(['patient', 'doctor.employ', 'nurse'])->findOrFail($this->view_id),
            ])->layout('admins.layouts.app');
        }

        if ($this->_page === 'create') {
            return view('livewire.admins.appiontment', [
                'patients' => patient::all(),
                'doctors' => doctor::with('employ')->get(),
                'nurses' => nurse::all(),
            ])->layout('admins.layouts.app');
        }

        $query = appointment::with(['patient', 'doctor.employ']);
        $this->scopeToOwnDoctor($query);

        return view('livewire.admins.appiontment', [
            'appointments' => $query->latest('intime')->paginate(10),
        ])->layout('admins.layouts.app');
    }

    /**
     * Doctor data scoping (RBAC spec Section 7): a user account linked to a
     * doctor profile only sees/manages their own appointments. Checking
     * doctor_id rather than the role name keeps this data-driven — any
     * future role with a linked doctor profile is scoped the same way.
     */
    protected function scopeToOwnDoctor($query)
    {
        $user = auth()->user();
        if ($user && $user->doctor_id) {
            $query->where('doctor_id', $user->doctor_id);
        }
    }

    protected function assertOwnsAppointment(appointment $appointment)
    {
        $user = auth()->user();
        if ($user && $user->doctor_id && $appointment->doctor_id !== $user->doctor_id) {
            abort(403, 'You do not have access to this appointment.');
        }
    }
}
