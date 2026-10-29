<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps user accounts identical in both apps (Aesthetics <-> Dental).
 * Every create, update and delete of a user here is written straight
 * into the sister app's `users` table, matched by email, with the role
 * matched by name. Writes go through the query builder, so the sister
 * app's own observer never fires and nothing bounces back.
 *
 * doctor_id is not copied: doctors are separate records in each app.
 */
class SisterUserSync
{
    public static bool $paused = false;

    public static function enabled(): bool
    {
        return !self::$paused && (bool) config('database.connections.sister.database');
    }

    public static function push(User $user, ?string $oldEmail = null): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $db = DB::connection('sister');
            $roleName = Role::whereKey($user->role_id)->value('name');
            $roleId = $roleName ? $db->table('roles')->where('name', $roleName)->value('id') : null;

            $row = [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'role_id' => $roleId,
                'is_active' => (int) $user->is_active,
                'can_switch_app' => (int) $user->can_switch_app,
                'updated_at' => now(),
            ];

            $existing = $db->table('users')->whereRaw('LOWER(email) = ?', [strtolower($oldEmail ?: $user->email)])->first()
                ?? $db->table('users')->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->first();

            if ($existing) {
                $db->table('users')->where('id', $existing->id)->update($row);
                if (!$user->is_active) {
                    $db->table('sessions')->where('user_id', $existing->id)->delete();
                }
            } else {
                $db->table('users')->insert($row + ['created_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Sister app user sync failed: ' . $e->getMessage(), ['email' => $user->email]);
        }
    }

    public static function remove(string $email): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $db = DB::connection('sister');
            $id = $db->table('users')->whereRaw('LOWER(email) = ?', [strtolower($email)])->value('id');
            if ($id) {
                $db->table('sessions')->where('user_id', $id)->delete();
                $db->table('users')->where('id', $id)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Sister app user delete failed: ' . $e->getMessage(), ['email' => $email]);
        }
    }
}
