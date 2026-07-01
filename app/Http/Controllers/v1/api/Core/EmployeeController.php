<?php

namespace App\Http\Controllers\v1\api\Core;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Controllers\v1\api\Traits\HasTrashedFilter;
use App\Http\Requests\Core\StoreEmployeeRequest;
use App\Http\Requests\Core\UpdateEmployeeRequest;
use App\Http\Resources\Core\EmployeeResource;
use App\Services\Core\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends BaseApiController
{
    use HasTrashedFilter;
    protected array $permissions = [
        'index'       => 'core.employee.view',
        'store'       => 'core.employee.create',
        'show'        => 'core.employee.show',
        'update'      => 'core.employee.update',
        'destroy'     => 'core.employee.destroy',
        'forceDelete' => 'core.employee.forceDelete',
        'restore'     => 'core.employee.restore',
    ];

    public function __construct(protected EmployeeService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only(['search', 'is_active', 'department']);
        $this->restrictToContext($request, $filters);

        $trashed = $this->extractTrashedFilter($request);

        $employees = $this->service->paginate($filters, $this->perPage($request), $trashed);

        return $this->success(
            EmployeeResource::collection($employees),
            $this->paginationMetadata($employees)
        );
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorizeAction($request);

        $this->enforcePolicy(
            $request,
            feature: 'core',
            limitResource: 'employee',
            modelClass: \App\Models\Core\Employee::class
        );

        $this->service->create($this->getValidatedData($request));

        return $this->created('Employee created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $employee = $this->service->find($id, $this->getActiveOrgId($request));

        $this->authorizeAction($request, $employee);

        return $this->success(new EmployeeResource($employee->load('organization')));
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = $this->service->find($id, $this->getActiveOrgId($request));

        $this->authorizeAction($request, $employee);

        $this->service->update($employee, $request->validated());

        return $this->updated('Employee updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $employee = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $employee);

        $this->service->delete($id, $orgId);

        return $this->deleted('Employee deleted successfully.');
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        $orgId = $this->getActiveOrgId($request);
        $employee = $this->service->find($id, $orgId, withTrashed: true);

        $this->authorizeAction($request, $employee);

        $this->service->forceDelete($id, $orgId);

        return $this->deleted('Employee permanently deleted.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);

        $orgId = $this->getActiveOrgId($request);
        $employee = $this->service->find($id, $orgId, withTrashed: true);

        $this->authorizeAction($request, $employee);

        $this->service->restore($id, $orgId);

        return $this->success(['message' => 'Employee restored successfully.']);
    }
}
