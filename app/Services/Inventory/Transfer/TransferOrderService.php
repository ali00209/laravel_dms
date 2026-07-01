<?php

namespace App\Services\Inventory\Transfer;

use App\Guards\StockFlowGuard;
use App\Models\Inventory\TransferOrder;
use App\Models\Inventory\TransferOrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransferOrderService
{
    public function __construct(
        protected TransferAllocationService $allocation,
        protected TransferDispatchService $dispatch,
        protected TransferReceiveService $receive,
        protected StockFlowGuard $stockFlowGuard,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(array $data): TransferOrder
    {
        return DB::transaction(function () use ($data) {

            $order = TransferOrder::create([
                ...$data,
                'status' => 'draft',
            ]);

            foreach ($data['items'] as $item) {

                $order->items()->create([
                    'organization_id'    => $order->organization_id,
                    'product_variant_id' => $item['product_variant_id'],
                    'inventory_batch_id' => $item['inventory_batch_id'] ?? null,
                    'quantity'           => $item['quantity'],
                    'unit_cost'          => $item['unit_cost'],
                    'line_total'         => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            return $order->load('items');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Allocate
    |--------------------------------------------------------------------------
    */

    public function allocate(TransferOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new RuntimeException("Only draft orders can be allocated.");
        }

        $this->stockFlowGuard->enforce(
            $order->organization_id,
            $order->source_location_type,
            $order->source_location_id,
            $order->destination_location_type,
            $order->destination_location_id,
        );

        DB::transaction(function () use ($order) {

            foreach ($order->items as $item) {
                $this->allocation->allocateItem($order, $item);
            }

            $order->update([
                'status' => 'approved',
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatch (Partial Supported)
    |--------------------------------------------------------------------------
    */

    public function dispatch(TransferOrder $order, array $dispatchItems, int $userId): void
    {
        if (! in_array($order->status, ['approved','in_transit'])) {
            throw new RuntimeException("Order not dispatchable.");
        }

        $this->stockFlowGuard->enforce(
            $order->organization_id,
            $order->source_location_type,
            $order->source_location_id,
            'transit',
            null,
        );

        DB::transaction(function () use ($order, $dispatchItems, $userId) {

            foreach ($dispatchItems as $payload) {
                $this->dispatch->dispatchItem($order, $payload, $userId);
            }

            $order->update([
                'status' => 'in_transit',
                'in_transit_by' => $userId,
                'in_transit_at' => now(),
            ]);
        });
    }

    public function dispatchSerials(TransferOrderItem $item, int $organizationId): void
    {
        throw new RuntimeException('Serial transfer dispatch not yet implemented — use UniversalStockEngine instead.');
    }


    /*
    |--------------------------------------------------------------------------
    | Receive (Partial Supported)
    |--------------------------------------------------------------------------
    */

    public function receive(TransferOrder $order, array $receiveItems, int $userId): void
    {
        if ($order->status !== 'in_transit') {
            throw new RuntimeException("Order not in transit.");
        }

        $this->stockFlowGuard->enforce(
            $order->organization_id,
            'transit',
            null,
            $order->destination_location_type,
            $order->destination_location_id,
        );

        DB::transaction(function () use ($order, $receiveItems, $userId) {

            foreach ($receiveItems as $payload) {
                $this->receive->receiveItem($order, $payload, $userId);
            }

            // If all received → completed
            if ($this->receive->isFullyReceived($order)) {
                $order->update([
                    'status' => 'completed',
                    'completed_by' => $userId,
                    'completed_at' => now(),
                ]);
            }
        });
    }

    public function receiveSerials(TransferOrderItem $item, int $destinationLocationId): void
    {
        throw new RuntimeException('Serial transfer receive not yet implemented — use UniversalStockEngine instead.');
    }

    protected function getTransitLocationId(int $organizationId): int
    {
        return \App\Models\Inventory\StockLocation::query()
            ->where('organization_id', $organizationId)
            ->where('type', 'transit') // add this column if missing
            ->value('id')
            ?? throw new RuntimeException("Transit location not configured.");
    }


}
