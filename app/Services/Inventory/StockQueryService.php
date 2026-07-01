<?php

namespace App\Services\Inventory;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\OutletSection;
use App\Models\Core\Warehouse;
use App\Models\Core\WarehouseSection;
use App\Models\Inventory\InventoryBalance;
use App\Models\Inventory\StockLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StockQueryService
{
    protected array $stockLocationIds = [];

    protected ?int $variantId = null;

    public function under(Organization|Branch|Warehouse|WarehouseSection|OutletSection $entity): static
    {
        $this->stockLocationIds = $this->resolveLocationIds($entity);

        return $this;
    }

    public function forVariant(int $variantId): static
    {
        $this->variantId = $variantId;

        return $this;
    }

    public function get(): Collection
    {
        if (empty($this->stockLocationIds)) {
            return Collection::empty();
        }

        $query = InventoryBalance::whereIn('stock_location_id', $this->stockLocationIds);

        if ($this->variantId) {
            $query->where('product_variant_id', $this->variantId);
        }

        return $query->get();
    }

    public function getGroupedByVariant(): Collection
    {
        if (empty($this->stockLocationIds)) {
            return Collection::empty();
        }

        $query = InventoryBalance::selectRaw(
            'product_variant_id, SUM(quantity) as total_quantity, SUM(reserved_quantity) as total_reserved_quantity'
        )
            ->whereIn('stock_location_id', $this->stockLocationIds)
            ->groupBy('product_variant_id');

        if ($this->variantId) {
            $query->where('product_variant_id', $this->variantId);
        }

        return $query->get();
    }

    protected function resolveLocationIds(Organization|Branch|Warehouse|WarehouseSection|OutletSection $entity): array
    {
        return match (true) {
            $entity instanceof Organization => $this->resolveForOrganization($entity),
            $entity instanceof Branch => $this->resolveForBranch($entity),
            $entity instanceof Warehouse => $this->resolveForWarehouse($entity),
            $entity instanceof WarehouseSection => $this->resolveForSection($entity),
            $entity instanceof OutletSection => $this->resolveForSection($entity),
            default => [],
        };
    }

    protected function resolveForOrganization(Organization $org): array
    {
        return StockLocation::where('organization_id', $org->id)->pluck('id')->toArray();
    }

    protected function resolveForBranch(Branch $branch): array
    {
        $warehouseIds = $branch->warehouses()->pluck('warehouses.id');
        $warehouseSectionIds = WarehouseSection::whereIn(
            'warehouse_id',
            $warehouseIds
        )->pluck('id');

        $outletIds = $branch->outlets()->pluck('outlets.id');
        $outletSectionIds = OutletSection::whereIn(
            'outlet_id',
            $outletIds
        )->pluck('id');

        return StockLocation::where(function ($q) use ($branch, $warehouseIds, $warehouseSectionIds, $outletSectionIds) {
            $q->where(function ($q) use ($branch) {
                $q->where('locatable_type', $branch->getMorphClass())
                  ->where('locatable_id', $branch->id);
            });

            if ($warehouseIds->isNotEmpty()) {
                $q->orWhere(function ($q) use ($warehouseIds) {
                    $q->where('locatable_type', (new Warehouse)->getMorphClass())
                      ->whereIn('locatable_id', $warehouseIds);
                });
            }

            if ($warehouseSectionIds->isNotEmpty()) {
                $q->orWhere(function ($q) use ($warehouseSectionIds) {
                    $q->where('locatable_type', (new WarehouseSection)->getMorphClass())
                      ->whereIn('locatable_id', $warehouseSectionIds);
                });
            }

            if ($outletSectionIds->isNotEmpty()) {
                $q->orWhere(function ($q) use ($outletSectionIds) {
                    $q->where('locatable_type', (new OutletSection)->getMorphClass())
                      ->whereIn('locatable_id', $outletSectionIds);
                });
            }
        })->pluck('id')->toArray();
    }

    protected function resolveForWarehouse(Warehouse $warehouse): array
    {
        $warehouseSectionIds = $warehouse->sections()->pluck('id');
        $outletSectionIds = OutletSection::whereIn(
            'outlet_id',
            $warehouse->outlets()->pluck('outlets.id')
        )->pluck('id');

        return StockLocation::where(function ($q) use ($warehouse, $warehouseSectionIds, $outletSectionIds) {
            $q->where(function ($q) use ($warehouse) {
                $q->where('locatable_type', $warehouse->getMorphClass())
                  ->where('locatable_id', $warehouse->id);
            });

            if ($warehouseSectionIds->isNotEmpty()) {
                $q->orWhere(function ($q) use ($warehouseSectionIds) {
                    $q->where('locatable_type', (new WarehouseSection)->getMorphClass())
                      ->whereIn('locatable_id', $warehouseSectionIds);
                });
            }

            if ($outletSectionIds->isNotEmpty()) {
                $q->orWhere(function ($q) use ($outletSectionIds) {
                    $q->where('locatable_type', (new OutletSection)->getMorphClass())
                      ->whereIn('locatable_id', $outletSectionIds);
                });
            }
        })->pluck('id')->toArray();
    }

    protected function resolveForSection(WarehouseSection|OutletSection $section): array
    {
        return StockLocation::where('locatable_type', $section->getMorphClass())
            ->where('locatable_id', $section->id)
            ->pluck('id')
            ->toArray();
    }
}
