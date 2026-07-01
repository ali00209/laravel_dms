<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Inventory\MiniResources\VariantMiniResource;
use App\Http\Resources\MiniResources\MiniBranchResource;
use App\Http\Resources\MiniResources\MiniWarehouseResource;
use App\Http\Resources\MiniResources\MiniOutletResource;
use App\Http\Resources\MiniResources\MiniOrganizationResource;

class ProductVariantPriceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'organization_id'    => $this->organization_id,
            'product_variant_id' => $this->product_variant_id,
            'priceable_type'     => $this->priceable_type,
            'priceable_id'       => $this->priceable_id,
            'cost_price'         => $this->cost_price !== null ? (float) $this->cost_price : null,
            'sale_price'         => $this->sale_price !== null ? (float) $this->sale_price : null,
            'is_override'        => $this->is_override,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'deleted_at'         => $this->deleted_at,

            'variant'    => new VariantMiniResource($this->variant),
            'organization'=> new MiniOrganizationResource($this->organization),
            'priceable'  => $this->resolvePriceable($this->priceable),
        ];
    }

    private function resolvePriceable($priceable): ?array
    {
        if (! $priceable) return null;

        $class = get_class($priceable);
        $alias = array_search($class, Relation::morphMap());

        $resource = match ($alias) {
            'branch'       => new MiniBranchResource($priceable),
            'warehouse'    => new MiniWarehouseResource($priceable),
            'outlet'       => new MiniOutletResource($priceable),
            'organization' => new MiniOrganizationResource($priceable),
            default        => null,
        };

        return $resource?->toArray(request()) ?? [
            'id'   => $priceable->id,
            'name' => $priceable->name ?? null,
        ];
    }
}

