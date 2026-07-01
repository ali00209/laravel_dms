<?php

namespace App\Http\Controllers\v1\api\Inventory;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Controllers\v1\api\Traits\HasTrashedFilter;
use App\Http\Requests\Inventory\BulkProductVariantDiscountRequest;
use App\Http\Requests\Inventory\UpdateProductVariantDiscountRequest;
use App\Http\Resources\Inventory\ProductVariantDiscountResource;
use App\Services\Inventory\ProductVariantDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantDiscountController extends BaseApiController
{
    use HasTrashedFilter;

    protected array $permissions = [
        'index'       => 'inventory.product.view',
        'store'       => 'inventory.product.create',
        'show'        => 'inventory.product.show',
        'update'      => 'inventory.product.update',
        'destroy'     => 'inventory.product.destroy',
        'restore'     => 'inventory.product.restore',
        'forceDelete' => 'inventory.product.forceDelete',
        'bulk'        => 'inventory.product.create',
    ];

    public function __construct(protected ProductVariantDiscountService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request);

        $filters = $request->only([
            'product_variant_id', 'discountable_type', 'discountable_id',
            'application_type', 'discount_type', 'is_active', 'search',
        ]);
        $this->restrictToContext($request, $filters);

        $trashed = $this->extractTrashedFilter($request);

        $items = $this->service->paginate($filters, $this->perPage($request), $trashed);

        return $this->success(
            ProductVariantDiscountResource::collection($items),
            $this->paginationMetadata($items)
        );
    }

    /**
     * Handles
     * - Single discount
     * - Uniform Bulk discount (same discount for multiple variants)
     * - Mixed Bulk discount (different discounts for multiple variants)
     */
    public function store(BulkProductVariantDiscountRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'inventory');

        $this->service->bulkUpsert($this->getValidatedData($request));

        return $this->created('Product Variant Discount created successfully.');
    }

    public function bulk(BulkProductVariantDiscountRequest $request): JsonResponse
    {
        $this->authorizeAction($request);
        $this->enforcePolicy($request, 'inventory');

        $this->service->bulkUpsert($this->getValidatedData($request));

        return $this->created('Bulk discount applied successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $product_variant_discount = $this->service->find($id, $this->getActiveOrgId($request));
        $this->authorizeAction($request, $product_variant_discount);

        return $this->success(new ProductVariantDiscountResource($product_variant_discount));
    }

    public function update(UpdateProductVariantDiscountRequest $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $product_variant_discount = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $product_variant_discount);
        $updated = $this->service->update($product_variant_discount, $request->validated());

        return $this->success('Discount updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $orgId = $this->getActiveOrgId($request);
        $product_variant_discount = $this->service->find($id, $orgId);

        $this->authorizeAction($request, $product_variant_discount);
        $this->service->delete($product_variant_discount);

        return $this->deleted('Product variant discount deleted successfully.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        // $this->ensureAdmin($request);
        $product_variant_discount = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $product_variant_discount);
        $this->service->restore($product_variant_discount);

        return $this->success(['message' => 'Product variant discount restored successfully.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);
        $product_variant_discount = $this->service->find($id, $this->getActiveOrgId($request), true);

        $this->authorizeAction($request, $product_variant_discount);
        $this->service->forceDelete($product_variant_discount);

        return $this->deleted('Product variant discount permanently deleted.');
    }
}
