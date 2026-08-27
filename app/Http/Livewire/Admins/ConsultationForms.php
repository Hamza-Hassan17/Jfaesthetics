<?php

namespace App\Http\Livewire\Admins;

use App\Models\ConsultationForm;
use App\Models\doctor;
use App\Models\patient;
use App\Traits\LogsActivity;
use Livewire\Component;
use Livewire\WithPagination;

class ConsultationForms extends Component
{
    use WithPagination;
    use LogsActivity;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';

    public $editing_id;
    public $view_id;

    public $patient_id;
    public $phone;
    public $consultant_id;
    public $consultation_date;
    public $consultation_for;
    public $medical_history;
    public $medical_history_other;
    public $female_status = [];
    public $declaration_confirmed = false;
    public $patient_signature_name;
    public $recommended_treatment;
    public $notes;

    public $quick_patient_name;
    public $quick_patient_phone;
    public $quick_patient_address;
    public $quick_patient_gender;
    public $quick_patient_age;
    public $quick_patient_bloodgroup;

    public const CONSULTATION_FOR_OPTIONS = [
        'Aesthetic Treatments',
        'Dental Treatments',
        'Hair Treatments',
        'Hair Transplant',
        'Plastic Surgery',
        'Cosmetic Surgery',
    ];

    public const MEDICAL_HISTORY_OPTIONS = [
        'Diabetes',
        'Hypertension',
        'Heart Disease',
        'Thyroid Disorder',
        'Asthma',
        'Bleeding Disorder',
        'Other',
    ];

    public const FEMALE_STATUS_OPTIONS = [
        'Pregnant',
        'Breastfeeding',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->patient_id = '';
        $this->phone = '';
        $this->consultant_id = '';
        $this->consultation_date = now()->format('Y-m-d');
        $this->consultation_for = '';
        $this->medical_history = '';
        $this->medical_history_other = '';
        $this->female_status = [];
        $this->declaration_confirmed = false;
        $this->patient_signature_name = '';
        $this->recommended_treatment = '';
        $this->notes = '';
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
        $this->assertOwnsForm(ConsultationForm::findOrFail($id));
        $this->view_id = $id;
        $this->_page = 'view';
    }

    public function updated($name, $value)
    {
        if ($name === 'patient_id' && $value !== '' && !$this->phone) {
            $patient = patient::find($value);
            if ($patient && $patient->phone) {
                $this->phone = $patient->phone;
            }
        }
    }

    /**
     * Doctor data scoping (RBAC spec Section 7): a user account linked to a
     * doctor profile only sees/manages consultation forms they consulted
     * on. Checking doctor_id rather than the role name keeps this
     * data-driven.
     */
    protected function scopeToOwnDoctor($query)
    {
        $user = auth()->user();
        if ($user && $user->doctor_id) {
            $query->where('consultant_id', $user->doctor_id);
        }
    }

    protected function assertOwnsForm(ConsultationForm $form)
    {
        $user = auth()->user();
        if ($user && $user->doctor_id && $form->consultant_id !== $user->doctor_id) {
            abort(403, 'You do not have access to this consultation form.');
        }
    }

    public function edit($id)
    {
        $form = ConsultationForm::findOrFail($id);
        $this->assertOwnsForm($form);

        $this->editing_id = $form->id;
        $this->patient_id = $form->patient_id;
        $this->phone = $form->phone;
        $this->consultant_id = $form->consultant_id;
        $this->consultation_date = optional($form->consultation_date)->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->consultation_for = $form->consultation_for;
        $this->medical_history = $form->medical_history;
        $this->medical_history_other = $form->medical_history_other;
        $this->female_status = $form->female_status ?? [];
        $this->declaration_confirmed = (bool) $form->declaration_confirmed;
        $this->patient_signature_name = $form->patient_signature_name;
        $this->recommended_treatment = $form->recommended_treatment;
        $this->notes = $form->notes;

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
        session()->flash('message', 'Patient added and selected for this form.');
    }

    public function save()
    {
        abort_unless(auth()->user()->hasPermission('consultation_form', $this->editing_id ? 'update' : 'create'), 403);

        $user = auth()->user();
        if ($user && $user->doctor_id) {
            // A doctor account can only ever save consultation forms under
            // their own name, regardless of what the (hidden) consultant
            // field holds.
            $this->consultant_id = $user->doctor_id;
        }

        $this->validate([
            'patient_id' => 'required',
            'phone' => 'nullable|string',
            'consultant_id' => 'nullable',
            'consultation_date' => 'required|date',
            'consultation_for' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'medical_history_other' => 'nullable|string',
            'female_status' => 'nullable|array',
            'declaration_confirmed' => 'nullable|boolean',
            'patient_signature_name' => 'nullable|string',
            'recommended_treatment' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $data = [
            'patient_id' => $this->patient_id,
            'phone' => $this->phone ?: null,
            'consultant_id' => $this->consultant_id ?: null,
            'consultation_date' => $this->consultation_date,
            'consultation_for' => $this->consultation_for ?: null,
            'medical_history' => $this->medical_history ?: null,
            'medical_history_other' => $this->medical_history_other,
            'female_status' => $this->female_status,
            'declaration_confirmed' => $this->declaration_confirmed,
            'patient_signature_name' => $this->patient_signature_name,
            'recommended_treatment' => $this->recommended_treatment,
            'notes' => $this->notes,
        ];

        if ($this->editing_id) {
            $existing = ConsultationForm::findOrFail($this->editing_id);
            $this->assertOwnsForm($existing);
            $existing->update($data);
            $savedId = $this->editing_id;
            $this->logActivity('updated', 'consultation_form', $savedId, "Updated consultation form for patient #{$this->patient_id}.");
            session()->flash('message', 'Consultation form updated successfully.');
        } else {
            $data['created_by'] = auth()->user()->name ?? null;
            $savedId = ConsultationForm::create($data)->id;
            $this->logActivity('created', 'consultation_form', $savedId, "Created consultation form for patient #{$this->patient_id}.");
            session()->flash('message', 'Consultation form saved successfully.');
        }

        $this->view_id = $savedId;
        $this->_page = 'view';
    }

    public function delete($id)
    {
        abort_unless(auth()->user()->hasPermission('consultation_form', 'delete'), 403);

        $form = ConsultationForm::findOrFail($id);
        $this->assertOwnsForm($form);
        $form->delete();
        $this->logActivity('deleted', 'consultation_form', $id, "Deleted consultation form #{$id}.");
        session()->flash('message', 'Consultation form deleted successfully.');
    }

    public function render()
    {
        if ($this->_page === 'view' && $this->view_id) {
            return view('livewire.admins.consultation-forms', [
                'form' => ConsultationForm::with(['patient', 'consultant.employ'])->findOrFail($this->view_id),
            ])->layout('admins.layouts.app');
        }

        if ($this->_page === 'create') {
            return view('livewire.admins.consultation-forms', [
                'patients' => patient::all(),
                'doctors' => doctor::with('employ')->get(),
            ])->layout('admins.layouts.app');
        }

        $query = ConsultationForm::with(['patient', 'consultant.employ']);
        $this->scopeToOwnDoctor($query);

        return view('livewire.admins.consultation-forms', [
            'forms' => $query->latest()->paginate(10),
        ])->layout('admins.layouts.app');
    }
}
