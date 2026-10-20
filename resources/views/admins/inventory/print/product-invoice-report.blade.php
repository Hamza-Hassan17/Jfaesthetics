@extends('admins.inventory.print.layout')

@section('title', 'Product Invoices Report')

@section('party')
    <p><strong>Period:</strong> {{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Start' }} &ndash; {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Today' }}</p>
@endsection

@section('meta')
    <h2>Product Invoices Report</h2>
    <p><strong>Printed:</strong> {{ now()->format('d M Y h:i A') }}</p>
@endsection

@section('body')
    <table>
        <thead><tr><th>PI no</th><th>Date</th><th>Patient / buyer</th><th>Patient invoice</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Unpaid</th></tr></thead>
        <tbody>
            @foreach ($rows as $pi)
                <tr>
                    <td>{{ $pi->pi_no }}</td>
                    <td>{{ $pi->created_at->format('d M Y') }}</td>
                    <td>{{ $pi->buyer_name }}</td>
                    <td>{{ $pi->invoice ? '#' . $pi->invoice->invoice_number : '-' }}</td>
                    <td class="num">{{ number_format($pi->grand_total, 2) }}</td>
                    <td class="num">{{ number_format($pi->paid_total, 2) }}</td>
                    <td class="num">{{ number_format($pi->unpaid_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="summary">
        <table>
            <tr><td>Product invoices</td><td class="value">{{ $rows->count() }}</td></tr>
            <tr><td>Total</td><td class="value">{{ number_format($rows->sum(fn ($pi) => $pi->grand_total), 2) }}</td></tr>
            <tr><td>Collected</td><td class="value">{{ number_format($rows->sum(fn ($pi) => $pi->paid_total), 2) }}</td></tr>
            <tr class="grand"><td>Unpaid (PKR)</td><td class="value">{{ number_format($rows->sum(fn ($pi) => $pi->unpaid_total), 2) }}</td></tr>
        </table>
    </div>
@endsection
