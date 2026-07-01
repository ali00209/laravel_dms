<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StoreExpenseClaimRequest;
use App\Http\Requests\Voucher\UpdateExpenseClaimRequest;
use App\Http\Resources\Voucher\ExpenseClaimResource;
use App\Services\Voucher\ExpenseClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseClaimController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'accounts.expense.view',
        'store' => 'accounts.expense.create',
        'show' => 'accounts.expense.show',
        'update' => 'accounts.expense.update',
        'destroy' => 'accounts.expense.delete',
        'restore' => 'accounts.expense.restore',
        'forceDelete' => 'accounts.expense.forceDelete',
        'post' => 'accounts.expense.post',
        'cancel' => 'accounts.expense.cancel',
    ];

    public function __construct(protected ExpenseClaimService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'payee_name', 'status', 'date_from', 'date_to',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            ExpenseClaimResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StoreExpenseClaimRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'accounts');

        $claim = $this->service->create($this->getValidatedData($request));

        return $this->created(new ExpenseClaimResource($claim), ['message' => 'Expense claim created successfully.']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $claim = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $claim);

        return $this->success(new ExpenseClaimResource($claim));
    }

    public function update(UpdateExpenseClaimRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $claim);

        $result = $this->service->update($claim, $request->validated());

        return $this->success(new ExpenseClaimResource($result), ['message' => 'Expense claim updated successfully.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $claim);
        $this->service->delete($claim);

        return $this->deleted('Expense claim deleted successfully.');
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $claim);

        $result = $this->service->post($claim);

        return $this->success(new ExpenseClaimResource($result), ['message' => 'Expense claim posted successfully.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $claim);

        $result = $this->service->cancel($claim, $request->reason);

        return $this->success(new ExpenseClaimResource($result), ['message' => 'Expense claim cancelled.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $claim);
        $this->service->restore($claim->id);

        return $this->success(['message' => 'Expense claim restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $claim = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $claim);
        $this->service->forceDelete($claim->id);

        return $this->deleted('Expense claim permanently deleted.');
    }
}
