<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\patient;
use App\Traits\LogsActivity;
use Livewire\Component;
use Livewire\WithPagination;

class Appiontment extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    /**
     * MediLife Clinics' Doctor Visit tab has exactly one fixed doctor —
     * looked up by email rather than a hardcoded ID so it stays correct
     * across environments/reseeds. See MediLifeDoctorSeeder.
     */
    const MEDILIFE_DOCTOR_EMAIL = 'drfabreennaz@hotmail.com';
    const CONSULTATION_FEE = 1500;

    public $_page = 'index';

    public $editing_id;
    public $view_id;

    public $patient_id;
    public $case_no;
    public $status;
    public $age;
    public $phone;
    public $location;
    public $intime;
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

    protected function medilifeDoctor()
    {
        return doctor::whereHas('employ', function ($q) {
            $q->where('email', self::MEDILIFE_DOCTOR_EMAIL);
        })->first();
    }

    protected function nextCaseNo()
    {
        $next = (appointment::max('id') ?? 0) + 1;
        return 'C-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->patient_id = '';
        $this->case_no = $this->nextCaseNo();
        $this->status = 'booked';
        $this->age = '';
        $this->phone = '';
        $this->location = '';
        $this->intime = now()->format('Y-m-d\TH:i');
        $this->description = 'Skin Treatment';
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
        $this->view_id = $id;
        $this->_page = 'view';
    }

    public function updated($name, $value)
    {
        if ($name === 'patient_id' && $value !== '') {
            $patient = patient::find($value);
            if ($patient) {
                if ($patient->age && !$this->age) {
                    $this->age = $patient->age;
                }
                if ($patient->phone && !$this->phone) {
                    $this->phone = $patient->phone;
                }
            }
        }
    }

    public function edit($id)
    {
        $appointment = appointment::findOrFail($id);
        $this->editing_id = $id;

        $this->patient_id = $appointment->patient_id;
        $this->case_no = $appointment->case_no;
        $this->status = $appointment->status;
        $this->age = $appointment->age;
        $this->phone = $appointment->phone;
        $this->location = $appointment->location;
        $this->intime = optional($appointment->intime)->format('Y-m-d\TH:i');
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
        if ($this->quick_patient_phone) {
            $this->phone = $this->quick_patient_phone;
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

        $doctor = $this->medilifeDoctor();
        abort_if(!$doctor, 500, 'MediLife doctor profile is not set up — run MediLifeDoctorSeeder.');

        $this->validate([
            'patient_id' => 'required|numeric',
            'case_no' => 'nullable|string',
            'status' => 'required|in:booked,completed,cancelled,no_show',
            'age' => 'nullable|numeric',
            'phone' => 'nullable|string',
            'location' => 'nullable|string',
            'intime' => 'required|date',
            'description' => 'required|string',
            'prescription' => 'nullable|string',
        ]);

        $data = [
            'patient_id' => $this->patient_id,
            'case_no' => $this->case_no ?: null,
            'status' => $this->status ?: 'booked',
            'doctor_id' => $doctor->id,
            'age' => $this->age ?: null,
            'phone' => $this->phone ?: null,
            'location' => $this->location ?: null,
            'intime' => $this->intime,
            'description' => $this->description,
            'prescription' => $this->prescription,
            'consultation_fee' => self::CONSULTATION_FEE,
        ];

        if ($this->editing_id) {
            $existing = appointment::findOrFail($this->editing_id);
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
        $appointment->delete();
        $this->logActivity('deleted', 'appointments', $id, "Deleted appointment #{$id}.");
        session()->flash('message', 'Appointment deleted successfully.');
    }

    public function render()
    {
        if ($this->_page === 'view' && $this->view_id) {
            return view('livewire.admins.appiontment', [
                'appointment' => appointment::with(['patient', 'doctor.employ'])->findOrFail($this->view_id),
            ])->layout('admins.layouts.app');
        }

        if ($this->_page === 'create') {
            return view('livewire.admins.appiontment', [
                'patients' => patient::all(),
                'medilifeDoctor' => $this->medilifeDoctor(),
            ])->layout('admins.layouts.app');
        }

        return view('livewire.admins.appiontment', [
            'appointments' => appointment::with(['patient', 'doctor.employ'])->latest('intime')->paginate(10),
        ])->layout('admins.layouts.app');
    }
}
