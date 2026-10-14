<div class="jfr-tabs">
    <a href="{{ route('admin_inventory_items') }}" class="{{ request()->routeIs('admin_inventory_items') ? 'active' : '' }}">Items</a>
    <a href="{{ route('admin_inventory_categories') }}" class="{{ request()->routeIs('admin_inventory_categories') ? 'active' : '' }}">Categories</a>
    <a href="{{ route('admin_inventory_adjustments') }}" class="{{ request()->routeIs('admin_inventory_adjustments') ? 'active' : '' }}">Adjustments</a>
</div>
