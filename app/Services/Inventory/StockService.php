<?php

namespace App\Services\Inventory;

use App\Guards\StockFlowGuard;
use App\Models\Inventory\StockLocation;
use App\Models\Vouchers\DocumentItem;
use App\Services\Inventory\Core\LocationService;
use App\Services\Inventory\Core\StockMovementService;

class StockService
{
    public function __construct(
        protected StockMovementService $movementService,
        protected OutboundStockEngine $outboundEngine,
        protected InboundStockEngine $inboundEngine,
        protected LocationService $locationService,
        protected StockFlowGuard $stockFlowGuard,
    ) {}

    public function reduceStockForDocument(string $referenceType, int $referenceId, ?int $userId = null): void
    {
        $document = app($referenceType)::findOrFail($referenceId);
        $locationId = $this->resolveLocationId($document);
        $orgId = $document->organization_id;

        $this->stockFlowGuard->enforce(
            $orgId,
            'stock_location',
            $locationId,
            $this->resolveToType($referenceType),
            $this->resolveToId($document),
        );

        $items = DocumentItem::where('document_type', $referenceType)
            ->where('document_id', $referenceId)
            ->get();

        foreach ($items as $item) {
            $this->outboundEngine->consume([
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->cost_of_goods_sold ?? $item->unit_price,
                'stock_location_id' => $locationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId,
            ]);
        }
    }

    public function increaseStockForDocument(string $referenceType, int $referenceId, ?int $userId = null): void
    {
        $document = app($referenceType)::findOrFail($referenceId);
        $locationId = $this->resolveLocationId($document);
        $orgId = $document->organization_id;

        $this->stockFlowGuard->enforce(
            $orgId,
            $this->resolveFromType($referenceType),
            $this->resolveFromId($document),
            'stock_location',
            $locationId,
        );

        $items = DocumentItem::where('document_type', $referenceType)
            ->where('document_id', $referenceId)
            ->get();

        foreach ($items as $item) {
            $this->inboundEngine->add([
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->cost_of_goods_sold ?? $item->unit_price,
                'stock_location_id' => $locationId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $userId,
            ]);
        }
    }

    protected function resolveLocationId($document): int
    {
        if (isset($document->stock_location_id)) {
            return $document->stock_location_id;
        }

        if (isset($document->warehouse_id)) {
            try {
                return $this->locationService->getLocationId(
                    $document->organization_id,
                    'warehouse',
                    $document->warehouse_id
                );
            } catch (\RuntimeException) {
                // fall through to default
            }
        }

        $default = StockLocation::where('organization_id', $document->organization_id)
            ->where('is_default', true)
            ->value('id');

        if ($default) {
            return $default;
        }

        throw new \RuntimeException("Unable to resolve stock location for document #{$document->id}");
    }

    protected function resolveToType(string $referenceType): string
    {
        return match ($referenceType) {
            'App\Models\Vouchers\Invoice',
            'App\Models\Vouchers\DeliveryNote' => 'customer',
            'App\Models\Vouchers\DebitNote' => 'supplier',
            'App\Models\Vouchers\TransferOrder' => 'transit',
            default => 'outbound',
        };
    }

    protected function resolveToId($document): ?int
    {
        return match (true) {
            isset($document->customer_id) => $document->customer_id,
            isset($document->supplier_id) => $document->supplier_id,
            default => null,
        };
    }

    protected function resolveFromType(string $referenceType): string
    {
        return match ($referenceType) {
            'App\Models\Vouchers\PurchaseBill',
            'App\Models\Vouchers\ReceiptNote' => 'supplier',
            'App\Models\Vouchers\CreditNote' => 'customer',
            'App\Models\Vouchers\TransferOrder' => 'transit',
            default => 'inbound',
        };
    }

    protected function resolveFromId($document): ?int
    {
        return match (true) {
            isset($document->supplier_id) => $document->supplier_id,
            isset($document->customer_id) => $document->customer_id,
            default => null,
        };
    }
}
