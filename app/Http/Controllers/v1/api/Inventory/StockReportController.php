<?php

namespace App\Http\Controllers\v1\api\Inventory;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Models\Inventory\InventoryBalance;
use App\Models\Inventory\InventoryLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockReportController extends BaseApiController
{
    protected array $permissions = [
        'stockStatus'    => 'inventory.product.view',
        'stockMovements' => 'inventory.product.view',
        'stockAging'     => 'inventory.product.view',
        'lowStock'       => 'inventory.product.view',
    ];

    public function stockStatus(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $orgId = $this->getActiveOrgId($request);

        $query = InventoryBalance::query()
            ->with(['stockLocation', 'variant.product', 'batch'])
            ->where('organization_id', $orgId);

        if ($request->filled('stock_location_id')) {
            $query->where('stock_location_id', $request->stock_location_id);
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('condition_id')) {
            $query->where('condition_id', $request->condition_id);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('variant', function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $items = $query->get();

        return $this->success([
            'items' => $items->map(function ($balance) {
                return [
                    'id' => $balance->id,
                    'stock_location' => $balance->stockLocation ? [
                        'id' => $balance->stockLocation->id,
                        'name' => $balance->stockLocation->name,
                        'code' => $balance->stockLocation->code,
                    ] : null,
                    'product_variant' => $balance->variant ? [
                        'id' => $balance->variant->id,
                        'sku' => $balance->variant->sku,
                        'barcode' => $balance->variant->barcode,
                        'product_name' => $balance->variant->product->name ?? null,
                    ] : null,
                    'batch' => $balance->batch ? [
                        'id' => $balance->batch->id,
                        'batch_number' => $balance->batch->batch_number,
                        'expiry_date' => $balance->batch->expiry_date?->format('Y-m-d'),
                    ] : null,
                    'quantity' => $balance->quantity,
                    'reserved_quantity' => $balance->reserved_quantity,
                    'available_quantity' => $balance->available_quantity,
                    'avg_cost' => $balance->avg_cost,
                    'min_stock' => $balance->min_stock,
                    'reorder_point' => $balance->reorder_point,
                ];
            }),
            'total_count' => $items->count(),
            'as_of_date' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function stockMovements(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $orgId = $this->getActiveOrgId($request);

        $query = InventoryLedger::query()
            ->with(['location', 'variant.product', 'batch', 'creator'])
            ->where('organization_id', $orgId);

        if ($request->filled('stock_location_id')) {
            $query->where('stock_location_id', $request->stock_location_id);
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $results = $query->orderBy('created_at', 'desc')
            ->paginate($this->perPage($request));

        $items = $results->map(function ($ledger) {
            return [
                'id' => $ledger->id,
                'stock_location' => $ledger->location ? [
                    'id' => $ledger->location->id,
                    'name' => $ledger->location->name,
                ] : null,
                'product_variant' => $ledger->variant ? [
                    'id' => $ledger->variant->id,
                    'sku' => $ledger->variant->sku,
                    'barcode' => $ledger->variant->barcode,
                    'product_name' => $ledger->variant->product->name ?? null,
                ] : null,
                'batch' => $ledger->batch ? [
                    'id' => $ledger->batch->id,
                    'batch_number' => $ledger->batch->batch_number,
                ] : null,
                'transaction_type' => $ledger->transaction_type,
                'quantity_in' => $ledger->quantity_in,
                'quantity_out' => $ledger->quantity_out,
                'unit_cost' => $ledger->unit_cost,
                'total_cost' => $ledger->total_cost,
                'reference_type' => $ledger->reference_type,
                'reference_id' => $ledger->reference_id,
                'created_by' => $ledger->creator ? [
                    'id' => $ledger->creator->id,
                    'name' => $ledger->creator->name,
                ] : null,
                'created_at' => $ledger->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success(
            $items,
            $this->paginationMetadata($results)
        );
    }

    public function stockAging(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $orgId = $this->getActiveOrgId($request);

        $query = InventoryBalance::query()
            ->with(['stockLocation', 'variant.product', 'batch'])
            ->where('organization_id', $orgId);

        if ($request->filled('stock_location_id')) {
            $query->where('stock_location_id', $request->stock_location_id);
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->product_variant_id);
        }

        $balances = $query->get();

        $items = [];
        $totalQuantity = 0;
        $totalValue = 0;
        $buckets = [
            '0_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '91_180' => 0,
            '181_365' => 0,
            '365+' => 0,
        ];

        foreach ($balances as $balance) {
            $ageInDays = null;
            if ($balance->batch && $balance->batch->created_at) {
                $ageInDays = now()->diffInDays($balance->batch->created_at);
            } elseif ($balance->created_at) {
                $ageInDays = now()->diffInDays($balance->created_at);
            }

            $totalQuantity += $balance->quantity;
            $totalValue += $balance->quantity * $balance->avg_cost;

            $items[] = [
                'id' => $balance->id,
                'stock_location' => $balance->stockLocation ? [
                    'id' => $balance->stockLocation->id,
                    'name' => $balance->stockLocation->name,
                ] : null,
                'product_variant' => $balance->variant ? [
                    'id' => $balance->variant->id,
                    'sku' => $balance->variant->sku,
                    'product_name' => $balance->variant->product->name ?? null,
                ] : null,
                'batch' => $balance->batch ? [
                    'id' => $balance->batch->id,
                    'batch_number' => $balance->batch->batch_number,
                    'created_at' => $balance->batch->created_at->format('Y-m-d'),
                ] : null,
                'quantity' => $balance->quantity,
                'avg_cost' => $balance->avg_cost,
                'age_in_days' => $ageInDays,
            ];

            if ($ageInDays !== null) {
                if ($ageInDays <= 30) {
                    $buckets['0_30'] += $balance->quantity;
                } elseif ($ageInDays <= 60) {
                    $buckets['31_60'] += $balance->quantity;
                } elseif ($ageInDays <= 90) {
                    $buckets['61_90'] += $balance->quantity;
                } elseif ($ageInDays <= 180) {
                    $buckets['91_180'] += $balance->quantity;
                } elseif ($ageInDays <= 365) {
                    $buckets['181_365'] += $balance->quantity;
                } else {
                    $buckets['365+'] += $balance->quantity;
                }
            }
        }

        return $this->success([
            'items' => $items,
            'summary' => [
                'total_quantity' => $totalQuantity,
                'total_value' => round($totalValue, 2),
                'aging_buckets' => $buckets,
            ],
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $orgId = $this->getActiveOrgId($request);

        $query = InventoryBalance::query()
            ->with(['stockLocation', 'variant.product', 'batch'])
            ->where('organization_id', $orgId)
            ->where(function ($q) {
                $q->whereColumn('quantity', '<=', 'min_stock')
                  ->orWhereColumn('quantity', '<=', 'reorder_point');
            });

        if ($request->filled('stock_location_id')) {
            $query->where('stock_location_id', $request->stock_location_id);
        }

        $items = $query->get();

        return $this->success([
            'items' => $items->map(function ($balance) {
                return [
                    'id' => $balance->id,
                    'stock_location' => $balance->stockLocation ? [
                        'id' => $balance->stockLocation->id,
                        'name' => $balance->stockLocation->name,
                    ] : null,
                    'product_variant' => $balance->variant ? [
                        'id' => $balance->variant->id,
                        'sku' => $balance->variant->sku,
                        'product_name' => $balance->variant->product->name ?? null,
                    ] : null,
                    'quantity' => $balance->quantity,
                    'min_stock' => $balance->min_stock,
                    'reorder_point' => $balance->reorder_point,
                    'available_quantity' => $balance->available_quantity,
                ];
            }),
            'count' => $items->count(),
        ]);
    }
}
