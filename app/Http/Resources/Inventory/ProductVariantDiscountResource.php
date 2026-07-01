<?php

namespace App\Http\Resources\Inventory;

use App\Http\Resources\Inventory\MiniResources\CategoryMiniResource;
use App\Http\Resources\Inventory\MiniResources\VariantMiniResource;
use App\Http\Resources\MiniResources\MiniBranchResource;
use App\Http\Resources\MiniResources\MiniOrganizationResource;
use App\Http\Resources\MiniResources\MiniOutletResource;
use App\Http\Resources\MiniResources\MiniWarehouseResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantDiscountResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'organization_id'    => $this->organization_id,
            'product_variant_id' => $this->product_variant_id,
            'discountable_type'  => $this->discountable_type,
            'discountable_id'    => $this->discountable_id,
            'application_type'   => $this->application_type,
            'discount_type'      => $this->discount_type,
            'value'              => (float) $this->value,
            'max_discount_amount'=> $this->max_discount_amount !== null ? (float) $this->max_discount_amount : null,
            'priority'           => $this->priority,
            'stackable'          => $this->stackable,
            'start_date'         => $this->start_date,
            'end_date'           => $this->end_date,
            'is_active'          => $this->is_active,
            'formatted_discount' => $this->formatted_discount,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'deleted_at'         => $this->deleted_at,

            'variant'     => new VariantMiniResource($this->variant),
            'organization'=> new MiniOrganizationResource($this->organization),
            'discountable'=> $this->resolveDiscountable($this->discountable),
        ];
    }

    private function resolveDiscountable($discountable): ?array
    {
        if (! $discountable) return null;

        $class = get_class($discountable);
        $alias = array_search($class, Relation::morphMap());

        $resource = match ($alias) {
            'branch'            => new MiniBranchResource($discountable),
            'warehouse'         => new MiniWarehouseResource($discountable),
            'outlet'            => new MiniOutletResource($discountable),
            'organization'      => new MiniOrganizationResource($discountable),
            'category'          => new CategoryMiniResource($discountable),
            default             => null,
        };

        return $resource?->toArray(request()) ?? [
            'id'   => $discountable->id,
            'name' => $discountable->name ?? null,
        ];
    }
}

