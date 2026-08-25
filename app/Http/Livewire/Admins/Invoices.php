<?php

namespace App\Http\Livewire\Admins;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\doctor;
use App\Models\medicine;
use App\Models\patient;
use App\Models\Service;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $_page = 'index';

    public $patient_id;
    public $doctor_id;
    public $notes;
    public $items = [];
    public $payments = [];

    public $view_invoice_id;

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

    protected function blankItem()
    {
        return [
            'type' => 'custom',
            'catalog_id' => '',
            'service' => '',
            'quantity' => 1,
            'session' => '',
            'service_charges' => 0,
            'discount_type' => 'flat',
            'discount_value' => 0,
        ];
    }

    public function resetForm()
    {
        $this->patient_id = '';
        $this->doctor_id = '';
        $this->notes = '';
        $this->items = [$this->blankItem()];
        $this->payments = [
            ['paid_on' => now()->format('Y-m-d'), 'amount' => 0, 'payment_mode' => 'Cash'],
        ];
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
        $this->view_invoice_id = $id;
        $this->_page = 'view';
    }

    public function add_item()
    {
        $this->items[] = $this->blankItem();
    }

    public function remove_item($index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function updated($name, $value)
    {
        if (preg_match('/^items\.(\d+)\.type$/', $name)) {
            $index = (int) explode('.', $name)[1];
            $this->items[$index]['catalog_id'] = '';
            $this->items[$index]['service'] = '';
            if ($this->items[$index]['type'] === 'custom') {
                $this->items[$index]['service_charges'] = 0;
            }
        }

        if (preg_match('/^items\.(\d+)\.catalog_id$/', $name) && $value !== '') {
            $index = (int) explode('.', $name)[1];
            $type = $this->items[$index]['type'];

            if ($type === 'service') {
                $catalogItem = Service::find($value);
                if ($catalogItem) {
                    $this->items[$index]['service'] = $catalogItem->name;
                    $this->items[$index]['service_charges'] = $catalogItem->price;
                }
            } elseif ($type === 'medicine') {
                $catalogItem = medicine::find($value);
                if ($catalogItem) {
                    $this->items[$index]['service'] = $catalogItem->name;
                    $this->items[$index]['service_charges'] = $catalogItem->price;
                }
            }
        }
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

        $this->quick_patient_name = '';
        $this->quick_patient_phone = '';
        $this->quick_patient_address = '';
        $this->quick_patient_gender = '';
        $this->quick_patient_age = '';
        $this->quick_patient_bloodgroup = '';

        $this->dispatchBrowserEvent('patient-quick-added');
        session()->flash('message', 'Patient added and selected for this invoice.');
    }

    public function add_payment()
    {
        $this->payments[] = ['paid_on' => now()->format('Y-m-d'), 'amount' => 0, 'payment_mode' => 'Cash'];
    }

    public function remove_payment($index)
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function getItemTotalsProperty()
    {
        return collect($this->items)->map(function ($item) {
            $subTotal = (float) $item['quantity'] * (float) $item['service_charges'];
            $discountValue = (float) ($item['discount_value'] ?? 0);
            $discountAmount = ($item['discount_type'] ?? 'flat') === 'percent'
                ? $subTotal * $discountValue / 100
                : $discountValue;
            $discountAmount = min($discountAmount, $subTotal);
            return [
                'sub_total' => $subTotal,
                'discount_amount' => $discountAmount,
                'after_discount' => $subTotal - $discountAmount,
            ];
        });
    }

    public function getGrandTotalProperty()
    {
        return $this->item_totals->sum('after_discount');
    }

    public function getPaidTotalProperty()
    {
        return collect($this->payments)->sum(fn ($p) => (float) $p['amount']);
    }

    public function generate_invoice()
    {
        $this->validate([
            'patient_id' => 'required',
            'doctor_id' => 'required',
            'items.*.service' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.session' => 'nullable|integer|between:1,10',
            'items.*.service_charges' => 'required|numeric|min:0',
            'items.*.discount_type' => 'required|in:flat,percent',
            'items.*.discount_value' => 'required|numeric|min:0',
            'payments.*.paid_on' => 'required|date',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.payment_mode' => 'required|string',
        ]);

        // Stock check up front so we can show a clean validation error
        // rather than a mid-transaction failure.
        foreach ($this->items as $index => $item) {
            if ($item['type'] === 'medicine' && $item['catalog_id']) {
                $med = medicine::find($item['catalog_id']);
                if (!$med || $med->quantity < $item['quantity']) {
                    $this->addError("items.$index.quantity", 'Not enough stock available for ' . ($med->name ?? 'this medicine') . ' (available: ' . ($med->quantity ?? 0) . ').');
                    return;
                }
            }
        }

        try {
            $invoice = DB::transaction(function () {
                $invoiceNumber = date('y') . str_pad(Invoice::withTrashed()->max('id') + 1, 5, '0', STR_PAD_LEFT);

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'patient_id' => $this->patient_id,
                    'doctor_id' => $this->doctor_id,
                    'printed_by' => auth()->user()->name ?? null,
                    'notes' => $this->notes,
                ]);

                foreach ($this->items as $item) {
                    $subTotal = (float) $item['quantity'] * (float) $item['service_charges'];
                    $discountValue = (float) $item['discount_value'];
                    $discountAmount = $item['discount_type'] === 'percent'
                        ? $subTotal * $discountValue / 100
                        : $discountValue;
                    $discountAmount = min($discountAmount, $subTotal);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'service_id' => $item['type'] === 'service' ? ($item['catalog_id'] ?: null) : null,
                        'medicine_id' => $item['type'] === 'medicine' ? ($item['catalog_id'] ?: null) : null,
                        'service' => $item['service'],
                        'quantity' => $item['quantity'],
                        'session_number' => $item['session'] ?: null,
                        'service_charges' => $item['service_charges'],
                        'discount_type' => $item['discount_type'],
                        'discount_value' => $discountValue,
                        'sub_total' => $subTotal,
                        'discount' => $discountAmount,
                        'after_discount' => $subTotal - $discountAmount,
                    ]);

                    if ($item['type'] === 'medicine' && $item['catalog_id']) {
                        $med = medicine::lockForUpdate()->findOrFail($item['catalog_id']);

                        if ($med->quantity < $item['quantity']) {
                            throw new \RuntimeException('Not enough stock for ' . $med->name);
                        }

                        $med->decrement('quantity', $item['quantity']);

                        StockMovement::create([
                            'medicine_id' => $med->id,
                            'change' => -$item['quantity'],
                            'reason' => 'invoice_deduction',
                            'reference_type' => Invoice::class,
                            'reference_id' => $invoice->id,
                            'user_id' => auth()->id(),
                        ]);
                    }
                }

                foreach ($this->payments as $payment) {
                    if ((float) $payment['amount'] <= 0) {
                        continue;
                    }
                    InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'paid_on' => $payment['paid_on'],
                        'amount' => $payment['amount'],
                        'payment_mode' => $payment['payment_mode'],
                    ]);
                }

                return $invoice;
            });
        } catch (\RuntimeException $e) {
            $this->addError('items', $e->getMessage());
            return;
        }

        session()->flash('message', 'Invoice generated successfully.');
        $this->view_invoice_id = $invoice->id;
        $this->_page = 'view';
    }

    public function add_payment_to_invoice()
    {
        $this->validate([
            'new_paid_on' => 'required|date',
            'new_amount' => 'required|numeric|min:0.01',
            'new_payment_mode' => 'required|string',
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->view_invoice_id,
            'paid_on' => $this->new_paid_on,
            'amount' => $this->new_amount,
            'payment_mode' => $this->new_payment_mode,
        ]);

        $this->new_amount = '';
        session()->flash('message', 'Payment recorded successfully.');
    }

    public $new_paid_on;
    public $new_amount;
    public $new_payment_mode = 'Cash';

    public function delete($id)
    {
        Invoice::findOrFail($id)->delete();
        session()->flash('message', 'Invoice deleted successfully.');
    }

    public function render()
    {
        if ($this->_page === 'view' && $this->view_invoice_id) {
            $this->new_paid_on = $this->new_paid_on ?: now()->format('Y-m-d');
            return view('livewire.admins.invoices', [
                'invoice' => Invoice::with(['items', 'payments', 'patient', 'doctor.employ'])->findOrFail($this->view_invoice_id),
            ])->layout('admins.layouts.app');
        }

        if ($this->_page === 'create') {
            return view('livewire.admins.invoices', [
                'patients' => patient::all(),
                'doctors' => doctor::with('employ')->get(),
                'services' => Service::all(),
                'medicines' => medicine::all(),
            ])->layout('admins.layouts.app');
        }

        return view('livewire.admins.invoices', [
            'invoices' => Invoice::with(['patient', 'doctor.employ'])->latest()->paginate(10),
        ])->layout('admins.layouts.app');
    }
}
