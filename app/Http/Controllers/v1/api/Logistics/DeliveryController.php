<?php

namespace App\Http\Controllers\v1\api\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Inventory\Delivery\DeliveryService;
use App\Services\Inventory\Delivery\RiderAccountabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected RiderAccountabilityService $riderService,
    ) {}

    public function assignRider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'source_location_id' => 'required|integer|exists:stock_locations,id',
            'destination_location_id' => 'required|integer|exists:stock_locations,id',
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'inventory_batch_id' => 'nullable|integer|exists:inventory_batches,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0',
            'reference_type' => 'required|string',
            'reference_id' => 'required|integer',
            'rider_id' => 'required|integer|exists:users,id',
        ]);

        $this->deliveryService->assignToRider($validated);

        return response()->json(['message' => 'Rider assigned successfully'], 200);
    }

    public function deliver(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'source_location_id' => 'required|integer|exists:stock_locations,id',
            'destination_location_id' => 'required|integer|exists:stock_locations,id',
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'inventory_batch_id' => 'nullable|integer|exists:inventory_batches,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $validated['reference_type'] = 'Delivery';
        $validated['reference_id'] = $id;

        $this->deliveryService->deliver($validated);

        return response()->json(['message' => 'Delivery completed'], 200);
    }

    public function riderStock(Request $request, int $riderId): JsonResponse
    {
        $locationId = $request->input('stock_location_id');

        $balances = \App\Models\Inventory\InventoryBalance::query()
            ->where('stock_location_id', $locationId)
            ->with('productVariant')
            ->get();

        return response()->json($balances);
    }
}
