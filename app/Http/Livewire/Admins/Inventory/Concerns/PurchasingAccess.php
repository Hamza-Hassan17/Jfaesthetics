<?php

namespace App\Http\Livewire\Admins\Inventory\Concerns;

use App\Models\User;

/**
 * Purchasing screens are full of costs, so each tab is visible only with
 * inventory.view_cost or that tab's own manage permission.
 */
trait PurchasingAccess
{
    public static function canSeePurchasingTab(?User $user, string $manageAction): bool
    {
        return $user && ($user->hasPermission('inventory', 'view_cost') || $user->hasPermission('inventory', $manageAction));
    }

    protected function authorizePurchasingTab(string $manageAction): void
    {
        abort_unless(self::canSeePurchasingTab(auth()->user(), $manageAction), 403, 'You do not have access to this page.');
    }

    /**
     * Livewire calls hydrate() on every follow-up request (actions, paging,
     * field updates), which never re-run mount(). Re-checking here means a
     * revoked permission takes effect on the next click, not the next
     * full page load.
     */
    public function hydrate(): void
    {
        $this->authorizePurchasingTab(static::TAB_PERMISSION);
    }

    protected function authorizeAction(string $action): void
    {
        abort_unless(auth()->user()->hasPermission('inventory', $action), 403);
    }
}
