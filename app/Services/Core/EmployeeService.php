<?php

namespace App\Services\Core;

use App\Exceptions\NotFoundException;
use App\Models\Core\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeService
{
    public function paginate(array $filters, int $perPage, ?string $trashed = null): LengthAwarePaginator
    {
        $query = Employee::query();

        if (isset($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $term = '%' . $filters['search'] . '%';
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('designation', 'like', $term)
                  ->orWhere('department', 'like', $term);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        return $query->latest()->paginate($perPage);
    }

    public function find(int $id, ?int $orgId = null, bool $withTrashed = false): Employee
    {
        $query = Employee::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        $employee = $query->find($id);

        if (!$employee) {
            throw new NotFoundException('Employee not found.');
        }

        return $employee;
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);
        return $employee;
    }

    public function delete(int $id, ?int $orgId = null): void
    {
        $this->find($id, $orgId)->delete();
    }

    public function restore(int $id, ?int $orgId = null): Employee
    {
        $employee = $this->find($id, $orgId, withTrashed: true);

        if (!$employee->trashed()) {
            throw new NotFoundException('Employee is already active.');
        }

        $employee->restore();
        return $employee;
    }

    public function forceDelete(int $id, ?int $orgId = null): void
    {
        $this->find($id, $orgId, withTrashed: true)->forceDelete();
    }
}
