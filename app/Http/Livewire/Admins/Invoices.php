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
use Illuminate\Pagination\LengthAwarePaginator;
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

    public $adding_new_for_index;
    public $new_catalog_name;
    public $new_catalog_price;
    public $new_catalog_code;
    public $new_catalog_quantity;

    public $search = '';
    public $filter_from = '';
    public $filter_to = '';
    public $filter_doctor_id = '';
    public $filter_patient_id = '';
    public $filter_status = '';
    public $filter_service = '';

    public function mount()
    {
        $this->resetForm();
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->filter_from = '';
        $this->filter_to = '';
        $this->filter_doctor_id = '';
        $this->filter_patient_id = '';
        $this->filter_status = '';
        $this->filter_service = '';
        $this->resetPage();
    }

    protected function currentFilters()
    {
        return [
            'search' => $this->search,
            'from' => $this->filter_from,
            'to' => $this->filter_to,
            'doctor_id' => $this->filter_doctor_id,
            'patient_id' => $this->filter_patient_id,
            'status' => $this->filter_status,
            'service' => $this->filter_service,
        ];
    }

    public static function queryFilteredInvoices(array $filters)
    {
        $search = $filters['search'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $doctorId = $filters['doctor_id'] ?? null;
        $patientId = $filters['patient_id'] ?? null;
        $service = $filters['service'] ?? null;
        $status = $filters['status'] ?? null;

        $invoices = Invoice::with(['patient', 'doctor.employ', 'items', 'payments'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('doctor.employ', fn ($q3) => $q3->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId))
            ->when($service, fn ($q) => $q->whereHas('items', fn ($q2) => $q2->where('service', $service)))
            ->latest()
            ->get();

        if ($status) {
            $invoices = $invoices->filter(function ($invoice) use ($status) {
                $unpaid = $invoice->unpaid_total;
                $paid = $invoice->paid_total;
                return match ($status) {
                    'paid' => $unpaid <= 0,
                    'unpaid' => $paid <= 0,
                    'partial' => $paid > 0 && $unpaid > 0,
                    default => true,
                };
            })->values();
        }

        return $invoices;
    }

    public function exportCsv()
    {
        $filtered = self::queryFilteredInvoices($this->currentFilters());

        return response()->streamDownload(function () use ($filtered) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice #', 'Patient', 'Doctor', 'Services', 'Grand Total', 'Paid', 'Unpaid', 'Created On', 'Status']);
            foreach ($filtered as $invoice) {
                $status = $invoice->unpaid_total <= 0 ? 'Paid' : ($invoice->paid_total > 0 ? 'Partial' : 'Unpaid');
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->patient->name ?? 'N/A',
                    $invoice->doctor->employ->name ?? 'N/A',
                    $invoice->items->pluck('service')->implode(', '),
                    number_format($invoice->grand_total, 2, '.', ''),
                    number_format($invoice->paid_total, 2, '.', ''),
                    number_format($invoice->unpaid_total, 2, '.', ''),
                    $invoice->created_at->format('d M Y'),
                    $status,
                ]);
            }
            fclose($handle);
        }, 'invoices-report-' . now()->format('Ymd-His') . '.csv');
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

    public function show_add_new($index)
    {
        $this->adding_new_for_index = $index;
        $this->new_catalog_name = '';
        $this->new_catalog_price = '';
        $this->new_catalog_code = '';
        $this->new_catalog_quantity = '';
    }

    public function cancel_add_new()
    {
        $this->adding_new_for_index = null;
    }

    public function save_new_catalog_item()
    {
        $index = $this->adding_new_for_index;
        $type = $this->items[$index]['type'];

        if ($type === 'service') {
            $this->validate([
                'new_catalog_name' => 'required|string',
                'new_catalog_price' => 'required|numeric|min:0',
            ]);

            $service = Service::create([
                'name' => $this->new_catalog_name,
                'price' => $this->new_catalog_price,
            ]);

            $this->items[$index]['catalog_id'] = $service->id;
            $this->items[$index]['service'] = $service->name;
            $this->items[$index]['service_charges'] = $service->price;
        } elseif ($type === 'medicine') {
            $this->validate([
                'new_catalog_name' => 'required|string',
                'new_catalog_price' => 'required|numeric|min:0',
                'new_catalog_code' => 'required|string',
                'new_catalog_quantity' => 'required|numeric|min:0',
            ]);

            $med = medicine::create([
                'name' => $this->new_catalog_name,
                'price' => $this->new_catalog_price,
                'code' => $this->new_catalog_code,
                'quantity' => $this->new_catalog_quantity,
            ]);

            if ($this->new_catalog_quantity > 0) {
                StockMovement::create([
                    'medicine_id' => $med->id,
                    'change' => $this->new_catalog_quantity,
                    'reason' => 'initial_stock',
                    'user_id' => auth()->id(),
                ]);
            }

            $this->items[$index]['catalog_id'] = $med->id;
            $this->items[$index]['service'] = $med->name;
            $this->items[$index]['service_charges'] = $med->price;
        }

        $this->adding_new_for_index = null;
        session()->flash('message', 'Added to catalog and selected on this line.');
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

        $filtered = self::queryFilteredInvoices($this->currentFilters());

        $summary = [
            'total_invoices' => $filtered->count(),
            'total_revenue' => $filtered->sum('grand_total'),
            'total_paid' => $filtered->sum('paid_total'),
            'outstanding' => $filtered->sum('unpaid_total'),
        ];

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pagedItems = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
        $invoicesPaginated = new LengthAwarePaginator($pagedItems, $filtered->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return view('livewire.admins.invoices', [
            'invoices' => $invoicesPaginated,
            'summary' => $summary,
            'doctors' => doctor::with('employ')->get(),
            'patients' => patient::all(),
            'services' => Service::all(),
        ])->layout('admins.layouts.app');
    }
}
