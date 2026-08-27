<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'is_active' => true,
            'role_id' => $this->superAdminRoleId(),
        ];
    }

    /**
     * Tests don't run the RBAC seeder, so factory-created users would
     * otherwise land in a permissionless role and get 403'd everywhere —
     * default factory users to an unrestricted Super Admin role instead,
     * seeding it (and every permission) on first use if it isn't there yet.
     */
    protected function superAdminRoleId()
    {
        $role = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['rank' => 0, 'is_system_role' => true]
        );

        if ($role->permissions()->count() === 0) {
            (new \Database\Seeders\RoleSeeder())->run();
            $role->refresh();
        }

        return $role->id;
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }
}
