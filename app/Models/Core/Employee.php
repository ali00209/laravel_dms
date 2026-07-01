<?php

namespace App\Models\Core;

use App\Contracts\ContextScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model implements ContextScope
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'monthly_salary',
        'phone',
        'email',
        'designation',
        'department',
        'hire_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    public function organizationId(): int
    {
        return $this->organization_id;
    }

    public function branchId(): ?int
    {
        return null;
    }

    public function warehouseId(): ?int
    {
        return null;
    }

    public function outletId(): ?int
    {
        return null;
    }

    public function getContextOrganizationId(): ?int
    {
        return $this->organization_id;
    }

    public function getContextName(): string
    {
        return $this->name;
    }
}
