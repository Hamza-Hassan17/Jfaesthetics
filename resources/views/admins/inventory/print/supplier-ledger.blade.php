@extends('admins.inventory.print.layout')

@section('title', 'Supplier Ledger - ' . $supplier->name)

@section('party')
    <p><strong>Supplier:</strong> {{ $supplier->name }}</p>
    @if ($supplier->phone)<p><strong>Phone:</strong> {{ $supplier->phone }}</p>@endif
    <p><strong>Credit days:</strong> {{ $supplier->credit_days }}</p>
@endsection

@section('meta')
    <h2>Supplier Ledger</h2>
    <p><strong>Period:</strong> {{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Start' }} &ndash; {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Today' }}</p>
    <p><strong>Printed:</strong> {{ now()->format('d M Y h:i A') }}</p>
@endsection

@section('body')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Document</th>
                <th class="num">Debit (we owe more)</th>
                <th class="num">Credit (we owe less)</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d M Y') : '' }}</td>
                    <td>{{ $row['document'] }}</td>
                    <td class="num">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td>
                    <td class="num">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td>
                    <td class="num">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr class="grand"><td>Balance owed (PKR)</td><td class="value">{{ number_format(end($rows)['balance'], 2) }}</td></tr>
        </table>
    </div>
@endsection
