@php
    $canSeeTab = fn ($action) => \App\Http\Livewire\Admins\Inventory\Concerns\PurchasingAccess::canSeePurchasingTab(auth()->user(), $action);
@endphp
<div class="jfr-tabs">
    <a href="{{ route('admin_inventory_items') }}" class="{{ request()->routeIs('admin_inventory_items') ? 'active' : '' }}">Items</a>
    <a href="{{ route('admin_inventory_categories') }}" class="{{ request()->routeIs('admin_inventory_categories') ? 'active' : '' }}">Categories</a>
    @if ($canSeeTab('manage_suppliers'))
        <a href="{{ route('admin_inventory_suppliers') }}" class="{{ request()->routeIs('admin_inventory_suppliers') ? 'active' : '' }}">Suppliers</a>
    @endif
    @if ($canSeeTab('manage_po'))
        <a href="{{ route('admin_inventory_purchase_orders') }}" class="{{ request()->routeIs('admin_inventory_purchase_orders') ? 'active' : '' }}">Purchase Orders</a>
    @endif
    @if ($canSeeTab('manage_grn'))
        <a href="{{ route('admin_inventory_receiving') }}" class="{{ request()->routeIs('admin_inventory_receiving') ? 'active' : '' }}">Receiving</a>
    @endif
    @if ($canSeeTab('supplier_payments'))
        <a href="{{ route('admin_inventory_supplier_payments') }}" class="{{ request()->routeIs('admin_inventory_supplier_payments') ? 'active' : '' }}">Supplier Payments</a>
    @endif
    <a href="{{ route('admin_inventory_product_invoices') }}" class="{{ request()->routeIs('admin_inventory_product_invoices') ? 'active' : '' }}">Product Invoices</a>
    <a href="{{ route('admin_inventory_adjustments') }}" class="{{ request()->routeIs('admin_inventory_adjustments') ? 'active' : '' }}">Adjustments</a>
</div>
