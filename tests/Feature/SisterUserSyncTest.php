<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SisterUserSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        config(['database.connections.sister' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $schema = Schema::connection('sister');
        $schema->create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); });
        $schema->create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->unsignedBigInteger('role_id')->nullable(); $t->boolean('is_active')->default(true);
            $t->boolean('can_switch_app')->default(false); $t->timestamps();
        });
        $schema->create('sessions', function (Blueprint $t) { $t->string('id'); $t->unsignedBigInteger('user_id')->nullable(); });
        // Sister role ids deliberately differ from ours.
        foreach (Role::orderByDesc('id')->pluck('name') as $name) {
            DB::connection('sister')->table('roles')->insert(['name' => $name]);
        }
    }

    private function sister(string $email)
    {
        return DB::connection('sister')->table('users')->where('email', $email)->first();
    }

    public function test_create_update_rename_and_delete_are_mirrored_with_the_same_role()
    {
        $role = Role::where('name', 'Receptionist')->first();
        $user = User::factory()->create(['email' => 'a@clinic.pk', 'role_id' => $role->id]);

        $row = $this->sister('a@clinic.pk');
        $this->assertSame($user->password, $row->password);
        $this->assertSame('Receptionist', DB::connection('sister')->table('roles')->where('id', $row->role_id)->value('name'));

        $user->update(['email' => 'b@clinic.pk', 'is_active' => false, 'role_id' => Role::where('name', 'Admin')->value('id')]);
        $this->assertNull($this->sister('a@clinic.pk'));
        $row = $this->sister('b@clinic.pk');
        $this->assertSame(0, (int) $row->is_active);
        $this->assertSame('Admin', DB::connection('sister')->table('roles')->where('id', $row->role_id)->value('name'));

        $user->delete();
        $this->assertNull($this->sister('b@clinic.pk'));
    }

    public function test_the_sync_command_makes_the_sister_list_identical()
    {
        User::factory()->create(['email' => 'keep@clinic.pk']);
        DB::connection('sister')->table('users')->insert(['name' => 'x', 'email' => 'extra@clinic.pk', 'password' => 'x']);

        $this->artisan('users:sync-sister')->assertExitCode(0);

        $this->assertNotNull($this->sister('keep@clinic.pk'));
        $this->assertNull($this->sister('extra@clinic.pk'));
    }
}
