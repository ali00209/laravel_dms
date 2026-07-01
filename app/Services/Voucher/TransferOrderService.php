<?php

namespace App\Services\Voucher;

use App\Models\Inventory\TransferOrder;
use App\Services\Inventory\Transfer\TransferOrderService as InventoryTransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransferOrderService
{
    public function __construct(
        protected InventoryTransferService $inventoryService
    ) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = TransferOrder::query();

        if (isset($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['source_location_id'])) {
            $query->where('source_location_id', $filters['source_location_id']);
        }

        if (isset($filters['destination_location_id'])) {
            $query->where('destination_location_id', $filters['destination_location_id']);
        }

        return $query->with(['organization', 'requestedBy', 'approvedBy', 'sourceLocation', 'destinationLocation', 'items'])->paginate($perPage);
    }

    public function find(int $id): ?TransferOrder
    {
        return TransferOrder::with(['organization', 'requestedBy', 'approvedBy', 'sourceLocation', 'destinationLocation', 'items'])->find($id);
    }

    public function create(array $data): TransferOrder
    {
        return $this->inventoryService->create($data);
    }

    public function update(int $id, array $data): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }

        $order->update($data);

        return $order->fresh();
    }

    public function delete(int $id): bool
    {
        $order = TransferOrder::find($id);

        return $order ? $order->delete() : false;
    }

    public function submit(int $id): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }
        $order->update(['status' => 'submitted', 'requested_by' => auth()->id(), 'requested_at' => now()]);
        return $order->fresh();
    }

    public function review(int $id): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }
        $order->update(['status' => 'reviewed']);
        return $order->fresh();
    }

    public function reject(int $id): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }
        $order->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now()]);
        return $order->fresh();
    }

    public function approve(int $id): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }

        $this->inventoryService->allocate($order);

        $fresh = $order->fresh();

        event(new \App\Events\Voucher\VoucherApproved([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'transfer_order',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->approved_by ?? ($fresh->created_by ?? null),
            'items_count' => $fresh->items()->count(),
        ]));

        return $fresh;
    }

    public function cancel(int $id): ?TransferOrder
    {
        $order = TransferOrder::find($id);
        if (! $order) {
            return null;
        }

        $order->update(['status' => 'cancelled']);

        $fresh = $order->fresh();
        event(new \App\Events\Voucher\VoucherCancelled([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'transfer_order',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $fresh->created_by ?? null,
            'reason' => 'Cancelled',
        ]));

        return $fresh;
    }

    public function dispatch(int $id, array $items, int $userId): ?TransferOrder
    {
        $order = TransferOrder::findOrFail($id);
        $this->inventoryService->dispatch($order, $items, $userId);
        $fresh = $order->fresh();
        event(new \App\Events\Voucher\DeliveryCompleted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'transfer_order',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $userId,
            'items_count' => count($items),
        ]));
        return $fresh;
    }

    public function receive(int $id, array $items, int $userId): ?TransferOrder
    {
        $order = TransferOrder::findOrFail($id);
        $this->inventoryService->receive($order, $items, $userId);
        $fresh = $order->fresh();
        event(new \App\Events\Voucher\DeliveryCompleted([
            'reference_type' => get_class($fresh),
            'reference_id' => $fresh->id,
            'document_type' => 'transfer_order_receive',
            'organization_id' => $fresh->organization_id ?? null,
            'branch_id' => $fresh->branch_id ?? null,
            'created_by' => $userId,
            'items_count' => count($items),
        ]));
        return $fresh;
    }
}
