<?php

namespace App\Services\Inventory;

use App\Support\RetryingTransaction;
use App\Models\DocumentSequence;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Supplier money: open bills, payment allocation, balances and the ledger.
 * The supplier's opening balance is treated as the oldest allocatable bill
 * (allocation rows with goods_receipt_id = null).
 */
class PayablesService
{
    public const OPENING = 'opening';

    /**
     * Bills with something still owed, oldest first: the opening balance,
     * then GRNs by receive date. $excluding ignores one payment's own
     * allocations (used while that payment is being edited).
     *
     * @return array<int, array{key: string, label: string, date: string, total: float, outstanding: float}>
     */
    public function openBills(Supplier $supplier, ?SupplierPayment $excluding = null): array
    {
        $bills = [];

        if ((float) $supplier->opening_balance > 0) {
            $paid = $this->allocatedTo($supplier->id, null, $excluding);
            $outstanding = round((float) $supplier->opening_balance - $paid, 2);
            if ($outstanding > 0) {
                $bills[] = [
                    'key' => self::OPENING,
                    'label' => 'Opening balance',
                    'date' => optional($supplier->created_at)->toDateString(),
                    'due_date' => optional($supplier->created_at)->toDateString(),
                    'total' => (float) $supplier->opening_balance,
                    'outstanding' => $outstanding,
                ];
            }
        }

        $receipts = GoodsReceipt::where('supplier_id', $supplier->id)->orderBy('receive_date')->orderBy('id')->get();
        foreach ($receipts as $receipt) {
            $outstanding = round((float) $receipt->total - $this->allocatedTo($supplier->id, $receipt->id, $excluding), 2);
            if ($outstanding > 0) {
                $bills[] = [
                    'key' => (string) $receipt->id,
                    'label' => $receipt->grn_no . ($receipt->supplier_bill_no ? " (bill {$receipt->supplier_bill_no})" : ''),
                    'date' => $receipt->receive_date->toDateString(),
                    'due_date' => $receipt->due_date->toDateString(),
                    'total' => (float) $receipt->total,
                    'outstanding' => $outstanding,
                ];
            }
        }

        return $bills;
    }

    /**
     * Default split of a payment: oldest open bill first.
     *
     * @return array<string, float> bill key => amount
     */
    public function suggestAllocations(Supplier $supplier, float $amount, ?SupplierPayment $excluding = null): array
    {
        $allocations = [];
        $left = round($amount, 2);

        foreach ($this->openBills($supplier, $excluding) as $bill) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, $bill['outstanding']);
            $allocations[$bill['key']] = $take;
            $left = round($left - $take, 2);
        }

        return $allocations;
    }

    /**
     * Creates or updates a payment with the given split. Anything not
     * allocated stays on the payment as an advance.
     *
     * @param array $data supplier_id, payment_date, amount, mode, reference_no, notes
     * @param array<string, float|string> $allocations bill key => amount
     */
    public function savePayment(array $data, array $allocations, ?SupplierPayment $payment = null): SupplierPayment
    {
        return RetryingTransaction::run(function () use ($data, $allocations, $payment) {
            $supplier = $this->lockSupplier($payment ? $payment->supplier_id : $data['supplier_id']);
            $amount = round((float) $data['amount'], 2);

            $allocations = collect($allocations)
                ->map(fn ($value) => round((float) $value, 2))
                ->filter(fn ($value) => $value > 0);

            if ($allocations->sum() - $amount > 0.004) {
                throw new DomainException('Allocations add up to more than the payment amount.');
            }

            $open = collect($this->openBills($supplier, $payment))->keyBy('key');
            foreach ($allocations as $key => $value) {
                $bill = $open->get((string) $key);
                if (!$bill || $value - $bill['outstanding'] > 0.004) {
                    throw new DomainException('An allocation is more than that bill\'s outstanding amount.');
                }
            }

            $touched = $payment ? $payment->allocations()->whereNotNull('goods_receipt_id')->pluck('goods_receipt_id')->all() : [];

            // supplier (held) → GRNs this payment changes, by id → payment number last.
            $this->lockReceipts(array_merge(
                $touched,
                $allocations->keys()->reject(fn ($key) => (string) $key === self::OPENING)->map(fn ($key) => (int) $key)->all()
            ));

            if ($payment) {
                $payment->update([
                    'payment_date' => $data['payment_date'],
                    'amount' => $amount,
                    'mode' => $data['mode'],
                    'reference_no' => $data['reference_no'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
                $payment->allocations()->delete();
            } else {
                $payment = SupplierPayment::create([
                    'payment_no' => DocumentSequence::next('SP'),
                    'supplier_id' => $supplier->id,
                    'payment_date' => $data['payment_date'],
                    'amount' => $amount,
                    'mode' => $data['mode'],
                    'reference_no' => $data['reference_no'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            foreach ($allocations as $key => $value) {
                $receiptId = (string) $key === self::OPENING ? null : (int) $key;
                $payment->allocations()->create(['goods_receipt_id' => $receiptId, 'amount' => $value, 'created_by' => auth()->id()]);
                if ($receiptId) {
                    $touched[] = $receiptId;
                }
            }

            $this->refreshReceipts($touched);

            return $payment->fresh('allocations');
        });
    }

    public function deletePayment(SupplierPayment $payment): void
    {
        RetryingTransaction::run(function () use ($payment) {
            $this->lockSupplier($payment->supplier_id);
            $touched = $payment->allocations()->whereNotNull('goods_receipt_id')->pluck('goods_receipt_id')->all();
            $this->lockReceipts($touched);
            $payment->allocations()->delete();
            $payment->delete();
            $this->refreshReceipts($touched);
        });
    }

    /**
     * Spec: an unallocated amount stays as an advance and auto-allocates
     * to the next GRN. Called when a GRN is received.
     */
    public function applyAdvances(GoodsReceipt $receipt): void
    {
        RetryingTransaction::run(function () use ($receipt) {
            $this->lockSupplier($receipt->supplier_id);
            $outstanding = round((float) $receipt->total - $this->allocatedTo($receipt->supplier_id, $receipt->id), 2);

            $payments = SupplierPayment::with('allocations')
                ->where('supplier_id', $receipt->supplier_id)
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                if ($outstanding <= 0) {
                    break;
                }
                $free = $payment->unallocated;
                if ($free <= 0) {
                    continue;
                }
                $take = min($free, $outstanding);
                $payment->allocations()->create(['goods_receipt_id' => $receipt->id, 'amount' => $take, 'created_by' => auth()->id()]);
                $outstanding = round($outstanding - $take, 2);
            }

            $this->refreshReceipts([$receipt->id]);
        });
    }

    /**
     * After a GRN's total drops below what was paid against it, the excess
     * is taken off its newest allocations and becomes advance again.
     */
    public function trimOverAllocation(GoodsReceipt $receipt): void
    {
        RetryingTransaction::run(function () use ($receipt) {
            $this->lockSupplier($receipt->supplier_id);
            $excess = round($this->allocatedTo($receipt->supplier_id, $receipt->id) - (float) $receipt->total, 2);

            $allocations = SupplierPaymentAllocation::where('goods_receipt_id', $receipt->id)->orderByDesc('id')->get();
            foreach ($allocations as $allocation) {
                if ($excess <= 0) {
                    break;
                }
                $cut = min($excess, (float) $allocation->amount);
                $remaining = round((float) $allocation->amount - $cut, 2);
                $remaining > 0 ? $allocation->update(['amount' => $remaining]) : $allocation->delete();
                $excess = round($excess - $cut, 2);
            }

            $this->refreshReceipts([$receipt->id]);
        });
    }

    /** Frees everything paid against a GRN (it becomes advance on the payments). */
    public function releaseReceipt(GoodsReceipt $receipt): void
    {
        RetryingTransaction::run(function () use ($receipt) {
            $this->lockSupplier($receipt->supplier_id);
            SupplierPaymentAllocation::where('goods_receipt_id', $receipt->id)->delete();
            $this->refreshReceipts([$receipt->id]);
        });
    }

    /**
     * Every write that reads "what's still outstanding" and then allocates
     * against it holds the supplier row, so two payments (or a payment and
     * a GRN receive) for the same supplier run one after the other instead
     * of both allocating the same outstanding amount.
     */
    private function lockSupplier(int $supplierId): Supplier
    {
        return Supplier::withTrashed()->lockForUpdate()->findOrFail($supplierId);
    }

    private function lockReceipts(array $receiptIds): void
    {
        $ids = collect($receiptIds)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->isNotEmpty()) {
            GoodsReceipt::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }
    }

    /** Recomputes the cached amount_paid / payment_status of GRNs. */
    public function refreshReceipts(array $receiptIds): void
    {
        foreach (array_unique(array_filter($receiptIds)) as $receiptId) {
            $receipt = GoodsReceipt::withTrashed()->find($receiptId);
            if (!$receipt) {
                continue;
            }
            $paid = round($this->allocatedTo($receipt->supplier_id, $receipt->id), 2);
            $total = round((float) $receipt->total, 2);

            $receipt->update([
                'amount_paid' => $paid,
                'payment_status' => $paid <= 0 && $total > 0 ? 'unpaid' : ($paid + 0.004 >= $total ? 'paid' : 'partial'),
            ]);
        }
    }

    /** Opening balance + GRN totals − payments (supplier returns join in Phase 4). */
    public function balance(Supplier $supplier): float
    {
        return round(
            (float) $supplier->opening_balance
            + (float) GoodsReceipt::where('supplier_id', $supplier->id)->sum('total')
            - (float) SupplierPayment::where('supplier_id', $supplier->id)->sum('amount'),
            2
        );
    }

    /**
     * Running-balance ledger. Rows before $from collapse into one
     * "Balance brought forward" row.
     *
     * @return array<int, array{date: ?string, document: string, debit: float, credit: float, balance: float, kind: string, id: ?int}>
     */
    public function ledger(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $entries = collect();

        foreach (GoodsReceipt::where('supplier_id', $supplier->id)->get() as $receipt) {
            $entries->push([
                'date' => $receipt->receive_date->toDateString(),
                'document' => $receipt->grn_no . ($receipt->supplier_bill_no ? ' / bill ' . $receipt->supplier_bill_no : ''),
                'debit' => (float) $receipt->total,
                'credit' => 0.0,
                'kind' => 'grn',
                'id' => $receipt->id,
                'sort' => $receipt->receive_date->toDateString() . sprintf('-1-%010d', $receipt->id),
            ]);
        }

        foreach (SupplierPayment::where('supplier_id', $supplier->id)->get() as $payment) {
            $entries->push([
                'date' => $payment->payment_date->toDateString(),
                'document' => $payment->payment_no . ' (' . (SupplierPayment::MODES[$payment->mode] ?? $payment->mode) . ')',
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'kind' => 'payment',
                'id' => $payment->id,
                'sort' => $payment->payment_date->toDateString() . sprintf('-2-%010d', $payment->id),
            ]);
        }

        $entries = $entries->sortBy('sort')->values();

        $balance = (float) $supplier->opening_balance;
        $rows = [[
            'date' => null,
            'document' => 'Opening balance',
            'debit' => max(0, $balance),
            'credit' => max(0, -$balance),
            'balance' => $balance,
            'kind' => 'opening',
            'id' => null,
        ]];

        foreach ($entries as $entry) {
            if ($to && $entry['date'] > $to) {
                break;
            }
            $balance = round($balance + $entry['debit'] - $entry['credit'], 2);

            if ($from && $entry['date'] < $from) {
                $rows[0] = ['date' => Carbon::parse($from)->toDateString(), 'document' => 'Balance brought forward', 'debit' => max(0, $balance), 'credit' => max(0, -$balance), 'balance' => $balance, 'kind' => 'opening', 'id' => null];
                continue;
            }

            unset($entry['sort']);
            $rows[] = $entry + ['balance' => $balance];
        }

        return $rows;
    }

    private function allocatedTo(int $supplierId, ?int $receiptId, ?SupplierPayment $excluding = null): float
    {
        return (float) SupplierPaymentAllocation::whereHas('payment', fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($receiptId === null, fn ($q) => $q->whereNull('goods_receipt_id'), fn ($q) => $q->where('goods_receipt_id', $receiptId))
            ->when($excluding, fn ($q) => $q->where('supplier_payment_id', '!=', $excluding->id))
            ->sum('amount');
    }
}
