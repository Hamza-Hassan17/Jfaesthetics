@extends('admins.inventory.print.layout')

@section('title', ($combined ? 'Invoice #' . $pi->invoice->invoice_number . ' + ' : '') . 'Product Invoice ' . $pi->pi_no)

@section('party')
    <p><strong>Name:</strong> {{ $pi->buyer_name }}</p>
    @if ($pi->buyer_phone)<p><strong>Phone:</strong> {{ $pi->buyer_phone }}</p>@endif
    @if ($pi->invoice)<p><strong>Patient invoice:</strong> #{{ $pi->invoice->invoice_number }}</p>@endif
@endsection

@section('meta')
    <h2>{{ $combined ? 'Invoice' : 'Product Invoice' }}</h2>
    @if ($combined)<p><strong>Invoice ID:</strong> {{ $pi->invoice->invoice_number }}</p>@endif
    <p><strong>Product invoice:</strong> {{ $pi->pi_no }}</p>
    <p><strong>Date:</strong> {{ $pi->created_at->format('d M Y') }}</p>
    <p><strong>Printed:</strong> {{ now()->format('d M Y h:i A') }}</p>
@endsection

@section('body')
    @if ($combined)
        @php $invoice = $pi->invoice; @endphp
        <h4 style="margin: 10px 0 4px;">Services — invoice #{{ $invoice->invoice_number }}</h4>
        <table>
            <thead><tr><th>Service</th><th>No. of Sessions</th><th class="num">Charges</th><th class="num">Discount</th><th class="num">Total</th></tr></thead>
            <tbody>
                @foreach ($invoice->items->filter(fn ($l) => $l->is_billable)->sortBy(fn ($l) => [$l->position, $l->id]) as $line)
                    <tr>
                        <td>{{ $line->service }}</td>
                        <td>{{ $line->line_type === 'product' ? '-' : ($line->session_number ?? '-') }}</td>
                        <td class="num">{{ number_format($line->sub_total, 2) }}</td>
                        <td class="num">{{ number_format($line->discount, 2) }}</td>
                        <td class="num">{{ number_format($line->after_discount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <table>
                <tr><td>Services total</td><td class="value">{{ number_format($invoice->grand_total, 2) }}</td></tr>
                <tr><td>Paid</td><td class="value">{{ number_format($invoice->paid_total, 2) }}</td></tr>
                <tr><td>Unpaid</td><td class="value">{{ number_format($invoice->unpaid_total, 2) }}</td></tr>
            </table>
        </div>
        <h4 style="margin: 14px 0 4px;">Products — {{ $pi->pi_no }}</h4>
    @endif

    {{-- Non-billable consumables never appear on the patient copy. --}}
    <table>
        <thead><tr><th>#</th><th>Product</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach ($pi->items->filter(fn ($l) => $l->is_billable)->values() as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->name }}</td>
                    <td class="num">{{ $line->quantity }}</td>
                    <td class="num">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="num">{{ number_format($line->discount, 2) }}</td>
                    <td class="num">{{ number_format($line->after_discount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="summary">
        <table>
            <tr><td>Products total</td><td class="value">{{ number_format($pi->grand_total, 2) }}</td></tr>
            <tr><td>Paid</td><td class="value">{{ number_format($pi->paid_total, 2) }}</td></tr>
            <tr><td>Unpaid</td><td class="value">{{ number_format($pi->unpaid_total, 2) }}</td></tr>
            @if ($combined)
                <tr class="grand"><td>Grand total (PKR)</td><td class="value">{{ number_format($pi->invoice->grand_total + $pi->grand_total, 2) }}</td></tr>
                <tr><td>Total unpaid</td><td class="value">{{ number_format($pi->invoice->unpaid_total + $pi->unpaid_total, 2) }}</td></tr>
            @endif
        </table>
    </div>

    @if ($pi->payments->isNotEmpty())
        <div class="notes"><strong>Payments ({{ $pi->pi_no }}):</strong>
            {{ $pi->payments->map(fn ($p) => $p->paid_on->format('d M Y') . ' ' . $p->payment_mode . ' ' . number_format($p->amount, 2))->implode(' · ') }}
        </div>
    @endif
@endsection
