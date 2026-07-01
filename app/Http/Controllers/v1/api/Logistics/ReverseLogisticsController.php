<?php

namespace App\Http\Controllers\v1\api\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Inventory\Delivery\ReverseLogisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReverseLogisticsController extends Controller
{
    public function __construct(
        protected ReverseLogisticsService $reverseService,
    ) {}

    public function initiateReturn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'source_location_id' => 'required|integer|exists:stock_locations,id',
            'original_location_id' => 'required|integer|exists:stock_locations,id',
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'inventory_batch_id' => 'nullable|integer|exists:inventory_batches,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0',
            'reference_type' => 'required|string',
            'reference_id' => 'required|integer',
        ]);

        $this->reverseService->initiateReturn($validated);

        return response()->json(['message' => 'Return initiated successfully'], 200);
    }
}
