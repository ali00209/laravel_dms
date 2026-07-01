<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\MiniResources\MiniOrganizationResource;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'monthly_salary' => (float) $this->monthly_salary,
            'phone' => $this->phone,
            'email' => $this->email,
            'designation' => $this->designation,
            'department' => $this->department,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,
            'organization' => new MiniOrganizationResource($this->whenLoaded('organization')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
