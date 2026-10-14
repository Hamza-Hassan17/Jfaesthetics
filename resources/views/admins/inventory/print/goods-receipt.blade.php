@extends('admins.inventory.print.layout')

@section('title', 'Goods Received Note ' . $receipt->grn_no)

@section('party')
    <p><strong>Supplier:</strong> {{ $receipt->supplier->name }}</p>
    @if ($receipt->supplier->phone)<p><strong>Phone:</strong> {{ $receipt->supplier->phone }}</p>@endif
    <p><strong>Supplier bill no:</strong> {{ $receipt->supplier_bill_no ?: '-' }}</p>
    <p><strong>Against P.O:</strong> {{ $receipt->purchaseOrder->po_no ?? 'Direct receive' }}</p>
@endsection

@section('meta')
    <h2>Goods Received Note</h2>
    <p><strong>GRN No:</strong> {{ $receipt->grn_no }}</p>
    <p><strong>Received:</strong> {{ $receipt->receive_date->format('d M Y') }}</p>
    <p><strong>Due:</strong> {{ $receipt->due_date->format('d M Y') }}</p>
    <p><strong>Payment:</strong> {{ ucfirst($receipt->payment_status) }}</p>
@endsection

@section('body')
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Batch no</th>
                <th>Expiry</th>
                <th class="num">Qty</th>
                <th class="num">Unit cost</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->items as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->item->name }} <small>({{ $line->item->code }})</small></td>
                    <td>{{ $line->batch_no }}</td>
                    <td>{{ $line->expiry_date ? $line->expiry_date->format('d M Y') : 'No expiry' }}</td>
                    <td class="num">{{ $line->qty }}</td>
                    <td class="num">{{ number_format($line->unit_cost, 2) }}</td>
                    <td class="num">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr><td>Subtotal</td><td class="value">{{ number_format($receipt->subtotal, 2) }}</td></tr>
            <tr><td>Discount</td><td class="value">- {{ number_format($receipt->discount, 2) }}</td></tr>
            <tr><td>Other charges</td><td class="value">{{ number_format($receipt->other_charges, 2) }}</td></tr>
            <tr class="grand"><td>Total (PKR)</td><td class="value">{{ number_format($receipt->total, 2) }}</td></tr>
            <tr><td>Paid</td><td class="value">{{ number_format($receipt->amount_paid, 2) }}</td></tr>
            <tr><td>Outstanding</td><td class="value">{{ number_format($receipt->outstanding, 2) }}</td></tr>
        </table>
    </div>

    @if ($receipt->notes)<div class="notes"><strong>Notes:</strong> {{ $receipt->notes }}</div>@endif

    <div class="signatures">
        <div>Received by: {{ $receipt->creator->name ?? '' }}</div>
        <div>Checked by</div>
    </div>
@endsection
