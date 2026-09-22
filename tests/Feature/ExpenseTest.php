<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Expenses;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    public function test_an_admin_can_record_an_expense()
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Expenses::class)
            ->call('show_create_modal')
            ->set('expense_date', now()->format('Y-m-d'))
            ->set('category', 'Petrol')
            ->set('description', 'Fuel for the delivery bike')
            ->set('payment_mode', 'Cash')
            ->set('amount', 1500)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'category' => 'Petrol',
            'payment_mode' => 'Cash',
            'amount' => 1500,
        ]);
    }

    public function test_category_and_amount_are_required()
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Expenses::class)
            ->call('show_create_modal')
            ->set('category', '')
            ->set('amount', '')
            ->call('save')
            ->assertHasErrors(['category', 'amount']);
    }

    public function test_payment_mode_must_be_cash_or_card()
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Expenses::class)
            ->call('show_create_modal')
            ->set('category', 'Chai')
            ->set('amount', 50)
            ->set('payment_mode', 'Crypto')
            ->call('save')
            ->assertHasErrors(['payment_mode']);
    }

    public function test_an_admin_can_edit_an_existing_expense()
    {
        $this->actingAs($this->adminUser());

        $expense = Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Coffee',
            'payment_mode' => 'Cash',
            'amount' => 300,
        ]);

        Livewire::test(Expenses::class)
            ->call('edit', $expense->id)
            ->assertSet('category', 'Coffee')
            ->set('amount', 350)
            ->call('save');

        $this->assertEquals(350, $expense->fresh()->amount);
    }

    public function test_an_admin_can_delete_an_expense()
    {
        $this->actingAs($this->adminUser());

        $expense = Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Tea',
            'payment_mode' => 'Card',
            'amount' => 100,
        ]);

        Livewire::test(Expenses::class)->call('delete', $expense->id);

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_expenses_page_filters_by_date_range()
    {
        $this->actingAs($this->adminUser());

        $oldExpense = Expense::create([
            'expense_date' => now()->subDays(10)->format('Y-m-d'),
            'category' => 'Old Expense',
            'payment_mode' => 'Cash',
            'amount' => 200,
        ]);

        $recentExpense = Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Recent Expense',
            'payment_mode' => 'Cash',
            'amount' => 400,
        ]);

        Livewire::test(Expenses::class)
            ->set('filter_from', now()->subDay()->format('Y-m-d'))
            ->set('filter_to', now()->addDay()->format('Y-m-d'))
            ->call('$refresh')
            ->assertSee('Recent Expense')
            ->assertDontSee('Old Expense');
    }

    public function test_a_role_without_expenses_permission_cannot_create_or_delete()
    {
        $nurse = Role::where('name', 'Nurse')->first();
        $user = User::factory()->create(['role_id' => $nurse->id, 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(Expenses::class)
            ->call('show_create_modal')
            ->assertStatus(403);
    }

    public function test_the_expenses_page_requires_the_expenses_permission()
    {
        $nurse = Role::where('name', 'Nurse')->first();
        $user = User::factory()->create(['role_id' => $nurse->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('admin_expenses'));

        $response->assertStatus(403);
    }

    public function test_an_admin_can_reach_the_expenses_page()
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin_expenses'));

        $response->assertStatus(200);
    }

    public function test_expense_print_view_renders()
    {
        $expense = Expense::create([
            'expense_date' => now()->format('Y-m-d'),
            'category' => 'Petrol',
            'payment_mode' => 'Cash',
            'amount' => 1200,
            'created_by' => 'Test Admin',
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin_expense_print', $expense->id));

        $response->assertStatus(200);
        $response->assertSee('Expense Voucher');
        $response->assertSee('Petrol');
        $response->assertSee('1,200.00');
    }
}
