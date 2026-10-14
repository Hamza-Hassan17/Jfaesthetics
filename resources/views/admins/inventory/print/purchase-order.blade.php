@extends('admins.inventory.print.layout')

@section('title', 'Purchase Order ' . $po->po_no)

@section('party')
    <p><strong>Supplier:</strong> {{ $po->supplier->name }}</p>
    @if ($po->supplier->contact_person)<p><strong>Contact:</strong> {{ $po->supplier->contact_person }}</p>@endif
    @if ($po->supplier->phone)<p><strong>Phone:</strong> {{ $po->supplier->phone }}</p>@endif
    @if ($po->supplier->address)<p><strong>Address:</strong> {{ $po->supplier->address }}</p>@endif
@endsection

@section('meta')
    <h2>Purchase Order</h2>
    <p><strong>P.O No:</strong> {{ $po->po_no }}</p>
    <p><strong>Order date:</strong> {{ $po->order_date->format('d M Y') }}</p>
    @if ($po->expected_date)<p><strong>Expected:</strong> {{ $po->expected_date->format('d M Y') }}</p>@endif
    <p><strong>Status:</strong> {{ \App\Models\PurchaseOrder::STATUSES[$po->status] ?? $po->status }}</p>
@endsection

@section('body')
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="num">Ordered</th>
                <th class="num">Received</th>
                @if ($po->status === 'closed')<th class="num">Closed short</th>@endif
                <th class="num">Unit cost</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->items as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->item->name }} <small>({{ $line->item->code }})</small></td>
                    <td class="num">{{ $line->qty_ordered }}</td>
                    <td class="num">{{ $line->qty_received }}</td>
                    @if ($po->status === 'closed')<td class="num">{{ $line->qty_outstanding }}</td>@endif
                    <td class="num">{{ number_format($line->unit_cost, 2) }}</td>
                    <td class="num">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr><td>Subtotal</td><td class="value">{{ number_format($po->subtotal, 2) }}</td></tr>
            <tr><td>Discount</td><td class="value">- {{ number_format($po->discount, 2) }}</td></tr>
            <tr><td>Other charges</td><td class="value">{{ number_format($po->other_charges, 2) }}</td></tr>
            <tr class="grand"><td>Total (PKR)</td><td class="value">{{ number_format($po->total, 2) }}</td></tr>
        </table>
    </div>

    @if ($po->notes)<div class="notes"><strong>Notes:</strong> {{ $po->notes }}</div>@endif
    @if ($po->status === 'closed')
        <div class="notes"><strong>Closed short</strong> on {{ $po->closed_at->format('d M Y') }}: {{ $po->closed_reason }}</div>
    @endif

    <div class="signatures">
        <div>Prepared by: {{ $po->creator->name ?? '' }}</div>
        <div>Authorised signature</div>
    </div>
@endsection
