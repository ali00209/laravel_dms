<?php

namespace App\Http\Resources\Inventory\MiniResources;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantMiniResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->product?->name,
            'sku'               => $this->sku,
            'barcode'           => $this->barcode,
        ];
    }
}
