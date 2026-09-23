<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\ExpenseReport;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function adminUser(): User
    {
        $admin = Role::where('name', 'Admin')->first();
        return User::factory()->create(['role_id' => $admin->id, 'is_active' => true]);
    }

    private function receptionistUser(): User
    {
        $receptionist = Role::where('name', 'Receptionist')->first();
        return User::factory()->create(['role_id' => $receptionist->id, 'is_active' => true]);
    }

    public function test_an_admin_can_reach_the_expense_report_page()
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin_reports_expenses'));

        $response->assertStatus(200);
    }

    public function test_expense_report_lists_expenses_within_the_filtered_date_range()
    {
        Expense::create([
            'expense_date' => now()->subDays(10)->format('Y-m-d'),
            'category' => 'Old Expense',
            'payment_mode' => 'Cash',
            'amount' => 200,
        ]);

        Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Recent Expense',
            'payment_mode' => 'Cash',
            'amount' => 400,
        ]);

        Livewire::actingAs($this->adminUser())
            ->test(ExpenseReport::class)
            ->set('filter_from', now()->subDay()->format('Y-m-d'))
            ->set('filter_to', now()->addDay()->format('Y-m-d'))
            ->call('$refresh')
            ->assertSee('Recent Expense')
            ->assertDontSee('Old Expense');
    }

    public function test_an_admin_can_print_the_expense_report()
    {
        Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Petrol',
            'payment_mode' => 'Cash',
            'amount' => 500,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin_reports_expenses_print_list'));

        $response->assertStatus(200);
        $response->assertSee('Expenses Report');
        $response->assertSee('Petrol');
    }

    public function test_an_admin_can_export_the_expense_report_as_csv()
    {
        Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Coffee',
            'payment_mode' => 'Card',
            'amount' => 250,
            'created_by' => 'Test Admin',
        ]);

        $this->actingAs($this->adminUser());

        $response = (new ExpenseReport())->exportCsv();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Coffee', $content);
    }

    public function test_a_role_without_reports_permission_cannot_reach_the_expense_report()
    {
        $nurse = Role::where('name', 'Nurse')->first();
        $user = User::factory()->create(['role_id' => $nurse->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('admin_reports_expenses'));

        $response->assertStatus(403);
    }

    public function test_a_receptionist_without_reports_permission_cannot_reach_the_expense_report()
    {
        // Receptionist has expenses.view (the tab itself) but not reports.*
        // (the Reports section) — those are two separate permission
        // modules, so seeing the Expenses tab must not imply seeing its
        // report.
        $response = $this->actingAs($this->receptionistUser())->get(route('admin_reports_expenses'));

        $response->assertStatus(403);
    }
}
