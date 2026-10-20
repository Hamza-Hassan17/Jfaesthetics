<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// testing gpush
Route::get('/', function () {
    return view('index');
});
Route::get('/about', function () {
    return view('about');
});
Route::get('/contact', function () {
    return view('contact');
});
Route::get("/docters", function () {
    return view('docter');
});
Route::get('/app', function () {
    return view('layouts.app');
});

Route::view('/services', 'services');

// Route::get('/admin/',[AdminController::class,'index'])->name("admins");

// Route::post('/admin/login',[AdminController::class,'authenticate_admin'])->name("admin_login");

Route::middleware(['auth', 'checksuperadmin'])->group(function () {
    Route::prefix('/admin')->group(function () {
        Route::get('/dashboard', App\Http\Livewire\Admins\Dashboard::class)->name('admin_dashboard')->middleware('permission:dashboard');

        /**
         * Backs the navbar's "Search anything..." live-search dropdown.
         * Filters per-module results by the user's own permissions rather
         * than gating the whole endpoint on one module, since it searches
         * across several.
         */
        Route::get('/search-suggestions', function (Illuminate\Http\Request $request) {
            $q = trim((string) $request->query('q', ''));
            if (mb_strlen($q) < 2) {
                return response()->json([]);
            }

            $user = $request->user();
            $results = [];

            if ($user->hasPermission('patients', 'view')) {
                App\Models\patient::where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->limit(5)
                    ->get()
                    ->each(function ($patient) use (&$results) {
                        $results[] = [
                            'type' => 'Patient',
                            'label' => $patient->name . ($patient->phone ? ' (' . $patient->phone . ')' : ''),
                            'url' => route('admin_patients', ['patient' => $patient->id]),
                        ];
                    });
            }

            if ($user->hasPermission('invoices', 'view')) {
                App\Models\Invoice::with('patient')
                    ->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn ($q2) => $q2->where('name', 'like', "%{$q}%"))
                    ->limit(5)
                    ->get()
                    ->each(function ($invoice) use (&$results) {
                        $results[] = [
                            'type' => 'Invoice',
                            'label' => '#' . $invoice->invoice_number . ' — ' . ($invoice->patient->name ?? 'N/A'),
                            'url' => route('admin_invoices', ['invoice' => $invoice->id]),
                        ];
                    });
            }

            return response()->json($results);
        })->name('admin_search_suggestions');
        Route::get('settings', App\Http\Livewire\Admins\Settings::class)->name('admin_settings')->middleware('permission:settings');
        Route::get('profile', App\Http\Livewire\Admins\Profile::class)->name('admin_profile');

        Route::get('roles-permissions', App\Http\Livewire\Admins\RolesPermissions::class)->name('admin_roles_permissions')->middleware('permission:roles');
        Route::get('nurses', App\Http\Livewire\Admins\Nurses::class)->name('nurses')->middleware('permission:legacy_modules');
        // Route::get('/docters', App\Http\Livewire\Admins\Docter::class)->name('admin_docters');
        Route::get('/operationsreport', App\Http\Livewire\Admins\Operationreport::class)->name('admin_operations_report')->middleware('permission:legacy_modules');
        Route::get('/patients', App\Http\Livewire\Admins\Patients::class)->name('admin_patients')->middleware('permission:patients');
        Route::get('/birthsreport', App\Http\Livewire\Admins\Birthreport::class)->name('admin_birth_report')->middleware('permission:legacy_modules');
        Route::get('/rooms', App\Http\Livewire\Admins\Rooms::class)->name('Rooms')->middleware('permission:legacy_modules');
        Route::get('/beds', App\Http\Livewire\Admins\Beds::class)->name('patients_beds')->middleware('permission:legacy_modules');

        Route::redirect('/inventory', '/admin/inventory/items')->name('admin_inventory');
        Route::get('/inventory/items', App\Http\Livewire\Admins\Inventory\Items::class)->name('admin_inventory_items')->middleware('permission:inventory');
        Route::get('/inventory/categories', App\Http\Livewire\Admins\Inventory\Categories::class)->name('admin_inventory_categories')->middleware('permission:inventory');
        Route::get('/inventory/adjustments', App\Http\Livewire\Admins\Inventory\Adjustments::class)->name('admin_inventory_adjustments')->middleware('permission:inventory');
        Route::get('/inventory/product-invoices', App\Http\Livewire\Admins\Inventory\ProductInvoices::class)->name('admin_inventory_product_invoices')->middleware('permission:inventory');
        Route::get('/inventory/product-invoices/{productInvoice}/print', function (Illuminate\Http\Request $request, $productInvoice) {
            $pi = App\Models\ProductInvoice::withTrashed()->with(['items', 'payments', 'patient', 'invoice.patient', 'invoice.items', 'invoice.payments'])->findOrFail($productInvoice);

            return view('admins.inventory.print.product-invoice', [
                'pi' => $pi,
                'combined' => $request->boolean('combined') && $pi->invoice,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_inventory_product_invoice_print')->middleware('permission:inventory');
        Route::get('/inventory/suppliers', App\Http\Livewire\Admins\Inventory\Suppliers::class)->name('admin_inventory_suppliers')->middleware('permission:inventory');
        Route::get('/inventory/purchase-orders', App\Http\Livewire\Admins\Inventory\PurchaseOrders::class)->name('admin_inventory_purchase_orders')->middleware('permission:inventory');
        Route::get('/inventory/receiving', App\Http\Livewire\Admins\Inventory\Receiving::class)->name('admin_inventory_receiving')->middleware('permission:inventory');
        Route::get('/inventory/supplier-payments', App\Http\Livewire\Admins\Inventory\SupplierPayments::class)->name('admin_inventory_supplier_payments')->middleware('permission:inventory');

        Route::get('/inventory/purchase-orders/{purchaseOrder}/print', function (App\Models\PurchaseOrder $purchaseOrder) {
            abort_unless(App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess::canSeePurchasingTab(auth()->user(), 'manage_po'), 403);

            return view('admins.inventory.print.purchase-order', [
                'po' => $purchaseOrder->load(['items.item', 'supplier', 'creator']),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_inventory_po_print')->middleware('permission:inventory');

        Route::get('/inventory/receiving/{goodsReceipt}/print', function ($goodsReceipt) {
            abort_unless(App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess::canSeePurchasingTab(auth()->user(), 'manage_grn'), 403);

            return view('admins.inventory.print.goods-receipt', [
                'receipt' => App\Models\GoodsReceipt::withTrashed()->with(['items.item', 'supplier', 'purchaseOrder', 'creator'])->findOrFail($goodsReceipt),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_inventory_grn_print')->middleware('permission:inventory');

        Route::get('/inventory/suppliers/{supplier}/ledger/print', function (Illuminate\Http\Request $request, App\Models\Supplier $supplier) {
            return view('admins.inventory.print.supplier-ledger', [
                'supplier' => $supplier,
                'rows' => app(App\Services\Inventory\PayablesService::class)->ledger($supplier, $request->query('from'), $request->query('to')),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_inventory_supplier_ledger_print')->middleware('permission:inventory,view_cost');

        Route::get('/inventory/suppliers/{supplier}/ledger/csv', function (Illuminate\Http\Request $request, App\Models\Supplier $supplier) {
            $rows = app(App\Services\Inventory\PayablesService::class)->ledger($supplier, $request->query('from'), $request->query('to'));

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Date', 'Document', 'Debit', 'Credit', 'Balance']);
                foreach ($rows as $row) {
                    fputcsv($out, [$row['date'], $row['document'], number_format($row['debit'], 2, '.', ''), number_format($row['credit'], 2, '.', ''), number_format($row['balance'], 2, '.', '')]);
                }
                fclose($out);
            }, 'supplier-ledger-' . Illuminate\Support\Str::slug($supplier->name) . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        })->name('admin_inventory_supplier_ledger_csv')->middleware('permission:inventory,view_cost');

        Route::get('/departments', App\Http\Livewire\Admins\Departments::class)->name('departments')->middleware('permission:legacy_modules');

        Route::get('/employees', App\Http\Livewire\Admins\Employees::class)->name('employees')->middleware('permission:employees');

        Route::get('/appointment', App\Http\Livewire\Admins\Appiontment::class)->name('appointment')->middleware('permission:appointments');

        Route::get('/appointment/{appointment}/print', function (App\Models\appointment $appointment) {
            $user = auth()->user();
            if ($user && $user->doctor_id && $appointment->doctor_id !== $user->doctor_id) {
                abort(403, 'You do not have access to this appointment.');
            }

            return view('admins.appointments.print', [
                'appointment' => $appointment->load(['patient', 'doctor.employ', 'nurse']),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_appointment_print')->middleware('permission:appointments');

        Route::get('/blocks', App\Http\Livewire\Admins\Blocks::class)->name('blocks')->middleware('permission:legacy_modules');

        Route::get('/hods', App\Http\Livewire\Admins\Hods::class)->name('hods')->middleware('permission:legacy_modules');

        Route::get('/requestedappointments', App\Http\Livewire\Admins\RequestedAppointments::class)->name('requestedAppointment')->middleware('permission:appointments');

        Route::get('/services', App\Http\Livewire\Admins\Services::class)->name('admin_services')->middleware('permission:services');

        Route::get('/subscribers', App\Http\Livewire\Admins\Subscibers::class)->name('subscibers')->middleware('permission:legacy_modules');

        Route::get('/contactedus', App\Http\Livewire\Admins\Contactedus::class)->name('contactedus')->middleware('permission:legacy_modules');

        Route::get('/invoices', App\Http\Livewire\Admins\Invoices::class)->name('admin_invoices')->middleware('permission:invoices');

        Route::get('/consultation-forms', App\Http\Livewire\Admins\ConsultationForms::class)->name('admin_consultation_forms')->middleware('permission:consultation_form');

        Route::get('/consultation-forms/{consultationForm}/print', function (App\Models\ConsultationForm $consultationForm) {
            $user = auth()->user();
            if ($user && $user->doctor_id && $consultationForm->consultant_id !== $user->doctor_id) {
                abort(403, 'You do not have access to this consultation form.');
            }

            return view('admins.consultation-forms.print', [
                'form' => $consultationForm->load(['patient', 'consultant.employ']),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_consultation_form_print')->middleware('permission:consultation_form');

        Route::get('/invoices/{invoice}/print', function (App\Models\Invoice $invoice) {
            return view('admins.invoices.print', [
                'invoice' => $invoice->load(['items', 'payments', 'patient', 'doctor.employ']),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_invoice_print')->middleware('permission:invoices');

        Route::get('/expenses', App\Http\Livewire\Admins\Expenses::class)->name('admin_expenses')->middleware('permission:expenses');

        Route::get('/expenses/{expense}/print', function (App\Models\Expense $expense) {
            return view('admins.expenses.print', [
                'expense' => $expense,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_expense_print')->middleware('permission:expenses');


        Route::get('/reports', App\Http\Livewire\Admins\Reports::class)->name('admin_reports')->middleware('permission:reports');

        Route::get('/reports/print', function (Illuminate\Http\Request $request) {
            $filters = $request->only(['search', 'from', 'to', 'doctor_id', 'patient_id', 'status', 'service']);

            return view('admins.invoices.print-list', [
                'invoices' => App\Http\Livewire\Admins\Reports::queryFilteredInvoices($filters),
                'filters' => $filters,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_reports_print_list')->middleware('permission:reports');

        Route::get('/reports/export', function (Illuminate\Http\Request $request) {
            $filters = $request->only(['search', 'from', 'to', 'doctor_id', 'patient_id', 'status', 'service']);
            $invoices = App\Http\Livewire\Admins\Reports::queryFilteredInvoices($filters);

            return response()->streamDownload(function () use ($invoices) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Invoice #', 'Patient', 'Doctor', 'Grand Total', 'Paid', 'Unpaid', 'Created On', 'Updated On', 'Status']);
                foreach ($invoices as $invoice) {
                    $unpaid = $invoice->unpaid_total;
                    $paid = $invoice->paid_total;
                    $status = $unpaid <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid');
                    fputcsv($out, [
                        $invoice->invoice_number,
                        optional($invoice->patient)->name,
                        optional(optional($invoice->doctor)->employ)->name,
                        number_format($invoice->grand_total, 2, '.', ''),
                        number_format($paid, 2, '.', ''),
                        number_format($unpaid, 2, '.', ''),
                        $invoice->created_at->format('Y-m-d'),
                        $invoice->updated_at->format('Y-m-d'),
                        $status,
                    ]);
                }
                fclose($out);
            }, 'reports-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        })->name('admin_reports_export')->middleware('permission:reports,export');

        Route::get('/reports/appointments', App\Http\Livewire\Admins\AppointmentReport::class)->name('admin_reports_appointments')->middleware('permission:reports');

        Route::get('/reports/appointments/print', function (Illuminate\Http\Request $request) {
            $filters = $request->only(['from', 'to', 'status', 'patient_id']);

            return view('admins.appointments.print-list', [
                'appointments' => App\Http\Livewire\Admins\AppointmentReport::queryFilteredAppointments($filters)->get(),
                'filters' => $filters,
            ]);
        })->name('admin_reports_appointments_print_list')->middleware('permission:reports');

        Route::get('/doctor-performance-report', App\Http\Livewire\Admins\DoctorPerformanceReport::class)->name('admin_doctor_performance_report')->middleware('permission:reports');

        Route::get('/doctor-performance-report/print', function (Illuminate\Http\Request $request) {
            $fromInput = $request->query('from') ?: now()->startOfMonth()->format('Y-m-d');
            $toInput = $request->query('to') ?: now()->endOfMonth()->format('Y-m-d');
            $doctorId = $request->query('doctor_id');

            return view('admins.doctor-performance-report.print', [
                'report' => App\Http\Livewire\Admins\DoctorPerformanceReport::buildReport($fromInput . ' 00:00:00', $toInput . ' 23:59:59', $doctorId),
                'from' => $fromInput,
                'to' => $toInput,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_doctor_performance_report_print')->middleware('permission:reports');

        Route::get('/reports/expenses', App\Http\Livewire\Admins\ExpenseReport::class)->name('admin_reports_expenses')->middleware('permission:reports');

        Route::get('/reports/product-invoices', App\Http\Livewire\Admins\ProductInvoiceReport::class)->name('admin_reports_product_invoices')->middleware('permission:reports');
        Route::get('/reports/product-invoices/print', function (Illuminate\Http\Request $request) {
            return view('admins.inventory.print.product-invoice-report', [
                'rows' => App\Http\Livewire\Admins\ProductInvoiceReport::filtered($request->only(['from', 'to', 'status'])),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_reports_product_invoices_print')->middleware('permission:reports');

        Route::get('/reports/expenses/print', function (Illuminate\Http\Request $request) {
            $filters = $request->only(['from', 'to']);

            return view('admins.expenses.print-list', [
                'expenses' => App\Http\Livewire\Admins\ExpenseReport::queryFilteredExpenses($filters)->get(),
                'filters' => $filters,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_reports_expenses_print_list')->middleware('permission:reports');
    });
});






Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
