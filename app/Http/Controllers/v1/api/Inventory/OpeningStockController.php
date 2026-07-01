<?php

namespace App\Http\Controllers\v1\api\Inventory;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Inventory\BulkOpeningStockRequest;
use App\Http\Requests\Inventory\StoreOpeningStockRequest;
use App\Services\Inventory\Core\ConditionService;
use App\Services\Inventory\InboundStockEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OpeningStockController extends BaseApiController
{
    protected array $permissions = [
        'store' => 'inventory.openingStock.create',
        'bulk' => 'inventory.openingStock.create',
    ];

    public function __construct(
        protected InboundStockEngine $inboundEngine,
        protected ConditionService $conditionService,
    ) {
        parent::__construct();
    }

    public function store(StoreOpeningStockRequest $request, int $stockLocation): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, feature: 'inventory');

        $data = $request->validated();
        $orgId = $this->getActiveOrgId($request);
        $conditionId = $this->conditionService->getIdByCode('GOOD', $orgId);

        $this->inboundEngine->add([
            'product_variant_id' => $data['product_variant_id'],
            'stock_location_id' => $stockLocation,
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'condition_id' => $conditionId,
            'batch_number' => $data['batch_number'] ?? null,
            'manufacturing_date' => $data['manufacturing_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'transaction_type' => 'opening_stock',
            'created_by' => auth()->id(),
        ]);

        return $this->created('Opening stock recorded successfully.');
    }

    public function bulk(BulkOpeningStockRequest $request, int $stockLocation): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, feature: 'inventory');

        $orgId = $this->getActiveOrgId($request);
        $conditionId = $this->conditionService->getIdByCode('GOOD', $orgId);

        DB::transaction(function () use ($request, $stockLocation, $conditionId) {
            foreach ($request->validated()['items'] as $item) {
                $this->inboundEngine->add([
                    'product_variant_id' => $item['product_variant_id'],
                    'stock_location_id' => $stockLocation,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'condition_id' => $conditionId,
                    'batch_number' => $item['batch_number'] ?? null,
                    'manufacturing_date' => $item['manufacturing_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'transaction_type' => 'opening_stock',
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return $this->created('Opening stock recorded successfully.');
    }
}
