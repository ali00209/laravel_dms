<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Requests\Voucher\StorePaymentRequest;
use App\Http\Requests\Voucher\UpdatePaymentRequest;
use App\Http\Resources\Voucher\PaymentResource;
use App\Services\Voucher\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseApiController
{
    protected array $permissions = [
        'index' => 'accounts.payment.view',
        'store' => 'accounts.payment.create',
        'show' => 'accounts.payment.show',
        'update' => 'accounts.payment.update',
        'destroy' => 'accounts.payment.delete',
        'restore' => 'accounts.payment.restore',
        'forceDelete' => 'accounts.payment.forceDelete',
        'post' => 'accounts.payment.post',
        'cancel' => 'accounts.payment.cancel',
        'allocate' => 'accounts.payment.allocate',
    ];

    public function __construct(protected PaymentService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'search', 'supplier_id', 'status', 'date_from', 'date_to',
            'payment_type', 'description',
        ]);

        $this->restrictToContext($request, $filters);

        $items = $this->service->paginate($filters, $this->perPage($request));

        return $this->success(
            PaymentResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'accounts');

        $payment = $this->service->create($this->getValidatedData($request));

        return $this->created(new PaymentResource($payment), ['message' => 'Payment created successfully.']);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payment = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $payment);

        return $this->success(new PaymentResource($payment));
    }

    public function update(UpdatePaymentRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $payment);

        $result = $this->service->update($payment, $request->validated());

        return $this->success(new PaymentResource($result), ['message' => 'Payment updated successfully.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $payment);
        $this->service->delete($payment);

        return $this->deleted('Payment deleted successfully.');
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $payment);

        $result = $this->service->post($payment);

        return $this->success(new PaymentResource($result), ['message' => 'Payment posted successfully.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $payment);

        $result = $this->service->cancel($payment, $request->reason);

        return $this->success(new PaymentResource($result), ['message' => 'Payment cancelled.']);
    }

    public function allocate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'references' => 'required|array',
            'references.*.reference_type' => 'required|string',
            'references.*.reference_id' => 'required|integer',
            'references.*.amount' => 'required|numeric|min:0.01',
        ]);

        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId);
        $this->authorizeAction($request, $payment);

        $result = $this->service->allocate($payment, $request->references);

        return $this->success(new PaymentResource($result), ['message' => 'Payment allocated successfully.']);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $payment);
        $this->service->restore($payment->id);

        return $this->success(['message' => 'Payment restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $orgId = $this->getActiveOrgId($request);
        $payment = $this->service->find($id, $orgId, true);

        $this->authorizeAction($request, $payment);
        $this->service->forceDelete($payment->id);

        return $this->deleted('Payment permanently deleted.');
    }
}
