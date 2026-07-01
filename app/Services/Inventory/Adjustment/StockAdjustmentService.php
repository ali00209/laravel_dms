<?php

namespace App\Services\Inventory\Adjustment;

use App\Guards\StockFlowGuard;
use App\Models\Inventory\StockAdjustment;
use App\Services\Inventory\Core\ConditionService;
use App\Services\Inventory\Core\InventoryBalanceService;
use App\Services\Inventory\Core\InventoryLedgerService;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        protected InventoryBalanceService $balanceService,
        protected InventoryLedgerService $ledgerService,
        protected ConditionService $conditionService,
        protected StockFlowGuard $stockFlowGuard,
    ) {}

    public function create(array $data): StockAdjustment
    {
        return StockAdjustment::create([
            'organization_id'       => $data['organization_id'],
            'stock_location_id'     => $data['stock_location_id'],
            'product_variant_id'    => $data['product_variant_id'],
            'inventory_batch_id'    => $data['inventory_batch_id'] ?? null,
            'adjustment_type'       => $data['adjustment_type'],
            'quantity'              => $data['quantity'],
            'unit_cost'             => $data['unit_cost'] ?? 0,
            'reason'                => $data['reason'] ?? null,
            'status'                => 'draft',
            'created_by'            => $data['created_by'] ?? auth()->id(),
        ]);
    }

    public function approve(int $id, ?int $userId = null): void
    {
        DB::transaction(function () use ($id, $userId) {
            $adjustment = StockAdjustment::findOrFail($id);

            if ($adjustment->status !== 'draft') {
                throw new \RuntimeException('Adjustment is not in draft status.');
            }

            $isAddition = $adjustment->adjustment_type === 'addition';

            $this->stockFlowGuard->enforce(
                $adjustment->organization_id,
                $isAddition ? 'inbound' : 'stock_location',
                $isAddition ? null : $adjustment->stock_location_id,
                $isAddition ? 'stock_location' : 'adjustment',
                $isAddition ? $adjustment->stock_location_id : null,
            );

            $quantity = $isAddition
                ? abs($adjustment->quantity)
                : -abs($adjustment->quantity);

            $goodConditionId = $this->conditionService->getIdByCode('GOOD', $adjustment->organization_id);

                $this->balanceService->adjust(
                    $adjustment->organization_id,
                    $adjustment->stock_location_id,
                    $adjustment->product_variant_id,
                    $adjustment->inventory_batch_id,
                    $goodConditionId,
                    (float) $quantity,
                    (float) $adjustment->unit_cost,
                );

            $this->ledgerService->record([
                'organization_id'    => $adjustment->organization_id,
                'stock_location_id'  => $adjustment->stock_location_id,
                'product_variant_id' => $adjustment->product_variant_id,
                'inventory_batch_id' => $adjustment->inventory_batch_id,
                'quantity_in'        => $quantity > 0 ? $quantity : 0,
                'quantity_out'       => $quantity < 0 ? abs($quantity) : 0,
                'unit_cost'          => $adjustment->unit_cost,
                'total_cost'         => $adjustment->unit_cost * abs($quantity),
                'transaction_type'   => 'adjustment_in',
                'reference_type'     => StockAdjustment::class,
                'reference_id'       => $adjustment->id,
                'created_by'         => $userId ?? auth()->id(),
            ]);

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $userId ?? auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }

    public function cancel(int $id, ?string $reason = null): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        if ($adjustment->status !== 'draft') {
            throw new \RuntimeException('Only draft adjustments can be cancelled.');
        }

        $adjustment->update([
            'status' => 'cancelled',
            'reason' => $reason,
        ]);
    }
}
