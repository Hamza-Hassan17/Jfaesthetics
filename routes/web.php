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
        Route::get('settings', App\Http\Livewire\Admins\Settings::class)->name('admin_settings')->middleware('permission:settings');

        Route::get('roles-permissions', App\Http\Livewire\Admins\RolesPermissions::class)->name('admin_roles_permissions')->middleware('permission:roles');
        Route::get('nurses', App\Http\Livewire\Admins\Nurses::class)->name('nurses')->middleware('permission:legacy_modules');
        // Route::get('/docters', App\Http\Livewire\Admins\Docter::class)->name('admin_docters');
        Route::get('/operationsreport', App\Http\Livewire\Admins\Operationreport::class)->name('admin_operations_report')->middleware('permission:legacy_modules');
        Route::get('/patients', App\Http\Livewire\Admins\Patients::class)->name('admin_patients')->middleware('permission:patients');
        Route::get('/birthsreport', App\Http\Livewire\Admins\Birthreport::class)->name('admin_birth_report')->middleware('permission:legacy_modules');
        Route::get('/rooms', App\Http\Livewire\Admins\Rooms::class)->name('Rooms')->middleware('permission:legacy_modules');
        Route::get('/beds', App\Http\Livewire\Admins\Beds::class)->name('patients_beds')->middleware('permission:legacy_modules');

        Route::get('/medicinesStore', App\Http\Livewire\Admins\Medicinestore::class)->name('medicinesStore')->middleware('permission:medicines_store');

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

        Route::get('/reports', App\Http\Livewire\Admins\Reports::class)->name('admin_reports')->middleware('permission:reports');

        Route::get('/reports/print', function (Illuminate\Http\Request $request) {
            $filters = $request->only(['search', 'from', 'to', 'doctor_id', 'patient_id', 'status', 'service']);

            return view('admins.invoices.print-list', [
                'invoices' => App\Http\Livewire\Admins\Reports::queryFilteredInvoices($filters),
                'filters' => $filters,
                'settings' => App\Models\Settings::pluck('value', 'key')->toArray(),
            ]);
        })->name('admin_reports_print_list')->middleware('permission:reports');

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
    });
});






Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
