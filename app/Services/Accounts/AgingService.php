<?php

namespace App\Services\Accounts;

use App\Models\Vouchers\Invoice;
use App\Models\Vouchers\PurchaseBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgingService
{
    public function accountsReceivable(int $organizationId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now()->format('Y-m-d');

        $invoices = Invoice::with('customer:id,name')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->select([
                'id', 'document_number', 'customer_id', 'date',
                'grand_total', 'paid_amount', 'due_amount', 'status',
            ])
            ->get();

        return $this->buildReport(
            items: $invoices,
            asOfDate: $asOfDate,
            entityField: 'customer',
            totalKey: 'total_receivable',
        );
    }

    public function accountsPayable(int $organizationId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? Carbon::now()->format('Y-m-d');

        $bills = PurchaseBill::with('supplier:id,name')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->select([
                'id', 'document_number', 'supplier_id', 'date',
                'grand_total', 'paid_amount', 'due_amount', 'status',
            ])
            ->get();

        return $this->buildReport(
            items: $bills,
            asOfDate: $asOfDate,
            entityField: 'supplier',
            totalKey: 'total_payable',
        );
    }

    private function buildReport(Collection $items, string $asOfDate, string $entityField, string $totalKey): array
    {
        $asOfDateCarbon = Carbon::parse($asOfDate);

        $buckets = [
            'current' => ['total' => 0.0, 'count' => 0, 'items' => []],
            '1_30'    => ['total' => 0.0, 'count' => 0, 'items' => []],
            '31_60'   => ['total' => 0.0, 'count' => 0, 'items' => []],
            '61_90'   => ['total' => 0.0, 'count' => 0, 'items' => []],
            '90_plus' => ['total' => 0.0, 'count' => 0, 'items' => []],
        ];

        $totalBalance = 0.0;
        $totalOverdue = 0.0;

        foreach ($items as $item) {
            $itemDate = $item->date instanceof Carbon ? $item->date : Carbon::parse($item->date);
            $daysOverdue = (int) $itemDate->diffInDays($asOfDateCarbon, false);

            $dueAmount = (float) $item->due_amount;
            $totalBalance += $dueAmount;

            $entry = [
                'id' => $item->id,
                'document_number' => $item->document_number,
                "{$entityField}_name" => $item->{$entityField}?->name ?? 'N/A',
                "{$entityField}_id" => $item->{"{$entityField}_id"},
                'date' => $item->date->format('Y-m-d'),
                'due_date' => null, // due_date not available on these models
                'total' => (float) $item->grand_total,
                'due_amount' => $dueAmount,
                'days_overdue' => max(0, $daysOverdue),
            ];

            if ($daysOverdue <= 0) {
                $buckets['current']['total'] += $dueAmount;
                $buckets['current']['count']++;
                $buckets['current']['items'][] = $entry;
            } elseif ($daysOverdue <= 30) {
                $buckets['1_30']['total'] += $dueAmount;
                $buckets['1_30']['count']++;
                $buckets['1_30']['items'][] = $entry;
                $totalOverdue += $dueAmount;
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60']['total'] += $dueAmount;
                $buckets['31_60']['count']++;
                $buckets['31_60']['items'][] = $entry;
                $totalOverdue += $dueAmount;
            } elseif ($daysOverdue <= 90) {
                $buckets['61_90']['total'] += $dueAmount;
                $buckets['61_90']['count']++;
                $buckets['61_90']['items'][] = $entry;
                $totalOverdue += $dueAmount;
            } else {
                $buckets['90_plus']['total'] += $dueAmount;
                $buckets['90_plus']['count']++;
                $buckets['90_plus']['items'][] = $entry;
                $totalOverdue += $dueAmount;
            }
        }

        // Round bucket totals
        foreach ($buckets as &$bucket) {
            $bucket['total'] = round($bucket['total'], 2);
        }
        unset($bucket);

        return [
            'buckets' => $buckets,
            $totalKey => round($totalBalance, 2),
            'total_overdue' => round($totalOverdue, 2),
            'as_of_date' => $asOfDate,
        ];
    }
}
