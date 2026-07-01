<?php

namespace App\Services\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\Journal;
use App\Models\Vouchers\Invoice;
use App\Models\Vouchers\PurchaseBill;

class AccountingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected AccountMappingService $accountMappingService
    ) {}

    public function createGlForInvoice(Invoice $invoice): void
    {
        $orgId = $invoice->organization_id;
        $items = $invoice->items;
        $totalAmount = (float) $items->sum('line_total');
        $totalCogs = (float) $items->sum('cost_of_goods_sold');

        if ($totalAmount <= 0) {
            return;
        }

        $receivableAccount = $this->accountMappingService->getReceivableAccount($orgId);
        $salesAccount = $this->accountMappingService->getSalesAccount($orgId);

        if (!$receivableAccount || !$salesAccount) {
            return;
        }

        $lines = [
            [
                'account_id' => $receivableAccount->id,
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => 'Invoice ' . $invoice->document_number,
            ],
            [
                'account_id' => $salesAccount->id,
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => 'Invoice ' . $invoice->document_number,
            ],
        ];

        if ($totalCogs > 0) {
            $cogsAccount = $this->accountMappingService->getCogsAccount($orgId);
            $inventoryAccount = $this->accountMappingService->getInventoryAccount($orgId);

            if ($cogsAccount && $inventoryAccount) {
                $lines[] = [
                    'account_id' => $cogsAccount->id,
                    'debit' => $totalCogs,
                    'credit' => 0,
                    'memo' => 'COGS for Invoice ' . $invoice->document_number,
                ];
                $lines[] = [
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $totalCogs,
                    'memo' => 'COGS for Invoice ' . $invoice->document_number,
                ];
            }
        }

        $this->journalEntryService->create([
            'organization_id' => $orgId,
            'financial_year_id' => $invoice->financial_year_id,
            'branch_id' => $invoice->branch_id,
            'date' => $invoice->date,
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
            'memo' => 'GL entry for Invoice ' . $invoice->document_number,
        ], $lines, autoPost: true);
    }

    public function createGlForPurchaseBill(PurchaseBill $bill): void
    {
        $orgId = $bill->organization_id;
        $totalAmount = (float) $bill->grand_total;

        if ($totalAmount <= 0) {
            return;
        }

        $inventoryAccount = $this->accountMappingService->getInventoryAccount($orgId);

        $payableAccount = Account::where('organization_id', $orgId)
            ->where('code', 'LIKE', '2%')
            ->where('is_active', true)
            ->first();

        if (!$inventoryAccount || !$payableAccount) {
            return;
        }

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit' => $totalAmount,
                'credit' => 0,
                'memo' => 'Purchase Bill ' . $bill->document_number,
            ],
            [
                'account_id' => $payableAccount->id,
                'debit' => 0,
                'credit' => $totalAmount,
                'memo' => 'Purchase Bill ' . $bill->document_number,
            ],
        ];

        $this->journalEntryService->create([
            'organization_id' => $orgId,
            'financial_year_id' => $bill->financial_year_id,
            'branch_id' => $bill->branch_id,
            'date' => $bill->date,
            'reference_type' => 'purchase_bill',
            'reference_id' => $bill->id,
            'memo' => 'GL entry for Purchase Bill ' . $bill->document_number,
        ], $lines, autoPost: true);
    }

    public function createJournalForVoucher(string $voucherType, int $voucherId, array $items, ?int $userId = null): Journal
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = [
                'account_id' => $item['account_id'],
                'debit' => $item['debit'] ?? 0,
                'credit' => $item['credit'] ?? 0,
                'memo' => $item['memo'] ?? null,
            ];
        }

        return $this->journalEntryService->create([
            'reference_type' => $voucherType,
            'reference_id' => $voucherId,
            'date' => now(),
            'memo' => 'Journal entry for ' . $voucherType . ' #' . $voucherId,
        ], $lines, autoPost: true);
    }

    public function postJournal(Journal $journal, ?int $userId = null): void
    {
        $this->journalEntryService->post($journal);
    }
}
