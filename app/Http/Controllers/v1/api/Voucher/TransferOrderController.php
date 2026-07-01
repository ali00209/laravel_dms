<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Voucher\StoreTransferOrderRequest;
use App\Http\Requests\Voucher\UpdateTransferOrderRequest;
use App\Http\Resources\Voucher\TransferOrderResource;
use App\Services\Voucher\TransferOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferOrderController extends Controller
{
    public function __construct(
        protected TransferOrderService $service
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['organization_id', 'status', 'source_location_id', 'destination_location_id']);
        $orders = $this->service->list($filters, $request->get('per_page', 15));
        return TransferOrderResource::collection($orders);
    }

    public function store(StoreTransferOrderRequest $request)
    {
        $order = $this->service->create($request->validated());
        return new TransferOrderResource($order);
    }

    public function show($id)
    {
        $order = $this->service->find($id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function update(UpdateTransferOrderRequest $request, $id)
    {
        $order = $this->service->update($id, $request->validated());
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function destroy($id)
    {
        if (! $this->service->delete($id)) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return response()->json(null, 204);
    }

    public function submit($id)
    {
        $order = $this->service->submit((int) $id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function review($id)
    {
        $order = $this->service->review((int) $id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function reject($id)
    {
        $order = $this->service->reject((int) $id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function approve($id)
    {
        $order = $this->service->approve($id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function cancel($id)
    {
        $order = $this->service->cancel($id);
        if (! $order) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return new TransferOrderResource($order);
    }

    public function dispatch(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $order = $this->service->dispatch((int) $id, $validated['items'], (int) auth()->id());
        return response()->json(new TransferOrderResource($order));
    }

    public function receive(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $order = $this->service->receive((int) $id, $validated['items'], (int) auth()->id());
        return response()->json(new TransferOrderResource($order));
    }

    public function restore($id)
    {
        $order = TransferOrder::withTrashed()->findOrFail($id);
        $order->restore();
        return new TransferOrderResource($order);
    }

    public function forceDelete($id)
    {
        $order = TransferOrder::withTrashed()->findOrFail($id);
        $order->forceDelete();
        return response()->json(null, 204);
    }
}
