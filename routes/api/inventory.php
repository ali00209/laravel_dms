<?php

/* =========================================================
 | INVENTORY CONTROLLERS
 ========================================================= */
use App\Http\Controllers\v1\api\Inventory\BrandController;
use App\Http\Controllers\v1\api\Inventory\BrandModelController;
use App\Http\Controllers\v1\api\Inventory\CartCalculationController;
use App\Http\Controllers\v1\api\Inventory\CategoryController;
use App\Http\Controllers\v1\api\Inventory\CouponController;
use App\Http\Controllers\v1\api\Inventory\InventoryBatchController;
use App\Http\Controllers\v1\api\Inventory\InventoryConditionController;
use App\Http\Controllers\v1\api\Inventory\OpeningStockController;
use App\Http\Controllers\v1\api\Inventory\PriceListController;
use App\Http\Controllers\v1\api\Inventory\PriceListItemController;
use App\Http\Controllers\v1\api\Inventory\PriceSimulationController;
use App\Http\Controllers\v1\api\Inventory\ProductController;
use App\Http\Controllers\v1\api\Inventory\ProductImageController;
use App\Http\Controllers\v1\api\Inventory\ProductVariantDiscountController;
use App\Http\Controllers\v1\api\Inventory\ProductVariantPriceController;
use App\Http\Controllers\v1\api\Inventory\PromotionController;
use App\Http\Controllers\v1\api\Inventory\StockLocationController;
use App\Http\Controllers\v1\api\Inventory\StockReportController;
use App\Http\Controllers\v1\api\Inventory\UnitController;
use App\Http\Controllers\v1\api\Inventory\VariationController;
use App\Http\Controllers\v1\api\Inventory\VariationValueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['ability:inventory.*'])->group(function () {

    Route::post('categories/{category}/restore', [CategoryController::class, 'restore']);
    Route::delete('categories/{category}/force', [CategoryController::class, 'forceDelete']);
    Route::apiResource('categories', CategoryController::class);

    Route::post('brands/{brand}/restore', [BrandController::class, 'restore']);
    Route::delete('brands/{brand}/force', [BrandController::class, 'forceDelete']);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('brands.models', BrandModelController::class)
        ->parameters(['models' => 'brand_model']);

    Route::prefix('brands/{brand}/models/{brand_model}')->group(function () {
        Route::patch('restore', [BrandModelController::class, 'restore']);
        Route::delete('force', [BrandModelController::class, 'forceDelete']);
    });

    Route::post('units/{unit}/restore', [UnitController::class, 'restore']);
    Route::delete('units/{unit}/force', [UnitController::class, 'forceDelete']);
    Route::apiResource('units', UnitController::class);

    Route::post('variations/{variation}/restore', [VariationController::class, 'restore']);
    Route::delete('variations/{variation}/force', [VariationController::class, 'forceDelete']);
    Route::apiResource('variations', VariationController::class);
    Route::apiResource('variations.values', VariationValueController::class)->parameters(['values'=> 'variation_values']);

    Route::prefix('variations/{variation}/values/{variation_value}')->group(function () {
        Route::patch('restore', [VariationValueController::class, 'restore']);
        Route::delete('force', [VariationValueController::class, 'forceDelete']);
    });

    Route::delete('products/{product}/force', [ProductController::class, 'forceDelete']);
    Route::patch('products/{product}/restore', [ProductController::class, 'restore']);
    Route::apiResource('products', ProductController::class);

    /*
    |--------------------------------------------------------------------------
    | Product Variant Prices
    |--------------------------------------------------------------------------
    */

    Route::prefix('product-variant-prices')->group(function () {
        Route::get('/', [ProductVariantPriceController::class, 'index']);
        Route::post('/bulk', [ProductVariantPriceController::class, 'bulk']);
        Route::post('/', [ProductVariantPriceController::class, 'store']);
        Route::get('/{id}', [ProductVariantPriceController::class, 'show']);
        Route::put('/{id}', [ProductVariantPriceController::class, 'update']);
        Route::delete('/{id}', [ProductVariantPriceController::class, 'destroy']);
        Route::post('/{id}/restore', [ProductVariantPriceController::class, 'restore']);
        Route::delete('/{id}/force', [ProductVariantPriceController::class, 'forceDelete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Product Variant Discounts
    |--------------------------------------------------------------------------
    */

    Route::prefix('product-variant-discounts')->group(function () {
        Route::get('/', [ProductVariantDiscountController::class, 'index']);
        Route::post('/bulk', [ProductVariantDiscountController::class, 'bulk']);
        Route::post('/', [ProductVariantDiscountController::class, 'store']);
        Route::get('/{id}', [ProductVariantDiscountController::class, 'show']);
        Route::put('/{id}', [ProductVariantDiscountController::class, 'update']);
        Route::delete('/{id}', [ProductVariantDiscountController::class, 'destroy']);
        Route::post('/{id}/restore', [ProductVariantDiscountController::class, 'restore']);
        Route::delete('/{id}/force', [ProductVariantDiscountController::class, 'forceDelete']);
    });

    // ====================== PRODUCT | VARIANT IMAGES ======================
    Route::post('{type}/{id}/images', [ProductImageController::class, 'store'])
        ->where('type', 'products|product-variants');

    // ====================== COMMON IMAGE OPERATIONS ======================
    Route::prefix('images')->group(function () {
        Route::put('{imageId}/set-primary', [ProductImageController::class, 'setPrimary']);
        Route::delete('{imageId}', [ProductImageController::class, 'destroy']);
        Route::post('{imageId}/restore', [ProductImageController::class, 'restore']);
        Route::delete('{imageId}/force-delete', [ProductImageController::class, 'forceDelete']);
    });

    // Inventory Module Routes
    Route::delete('stock_locations/{id}/force', [StockLocationController::class, 'forceDelete']);
    Route::patch('stock_locations/{id}/restore', [StockLocationController::class, 'restore']);
    Route::apiResource('stock_locations', StockLocationController::class);
    Route::post('stock_locations/{stock_location}/opening-stock', [OpeningStockController::class, 'store']);
    Route::post('stock_locations/{stock_location}/opening-stock/bulk', [OpeningStockController::class, 'bulk']);

    Route::post('inventory_batches/{inventory_batch}/restore', [InventoryBatchController::class, 'restore']);
    Route::delete('inventory_batches/{inventory_batch}/force', [InventoryBatchController::class, 'forceDelete']);
    Route::get('inventory_batches/by-variant/{product_variant_id}', [InventoryBatchController::class, 'byVariant']);
    Route::get('inventory_batches/expired', [InventoryBatchController::class, 'getExpired']);
    Route::get('inventory_batches/expiring', [InventoryBatchController::class, 'getExpiring']);
    Route::apiResource('inventory_batches', InventoryBatchController::class);

    // Price List
    Route::get('price_lists/{price_list}/items', [PriceListItemController::class, 'index'])->name('price_lists.items.index');
    Route::post('price_lists/{price_list}/items', [PriceListItemController::class, 'store'])->name('price_lists.items.store');
    Route::post('price_lists/{price_list}/restore', [PriceListController::class, 'restore']);
    Route::delete('price_lists/{price_list}/force', [PriceListController::class, 'forceDelete']);
    Route::apiResource('price_lists', PriceListController::class);

    Route::post('price_list_items/{id}/restore', [PriceListItemController::class, 'restore']);
    Route::delete('price_list_items/{id}/force', [PriceListItemController::class, 'forceDelete']);
    Route::apiResource('price_list_items', PriceListItemController::class)->except(['index', 'store']); // Exclude index and store since they are nested under price_lists

    /*
    |--------------------------------------------------------------------------
    | Coupons
    |--------------------------------------------------------------------------
    */

    // Custom routes for coupons (must come after apiResource to avoid conflicts)
    Route::prefix('coupons')->group(function () {
        // These routes don't conflict with apiResource because they're more specific
        Route::post('validate', [CouponController::class, 'validateCoupon']);
        Route::post('apply', [CouponController::class, 'applyCoupon']);
        Route::get('statistics', [CouponController::class, 'statistics']);
        Route::get('customers/{customerId}/coupons', [CouponController::class, 'customerCoupons']);

        // Custom actions for specific coupons
        Route::prefix('{coupon}')->group(function () {
            Route::post('duplicate', [CouponController::class, 'duplicate']);
            Route::put('toggle-status', [CouponController::class, 'toggleStatus']);
            Route::post('bulk-assign', [CouponController::class, 'bulkAssign']);
            Route::post('restore', [CouponController::class, 'restore']);
            Route::delete('force', [CouponController::class, 'forceDelete']);
        });
    });
    // Option 1: Using apiResource (simpler, follows product pattern)
    Route::apiResource('coupons', CouponController::class);


    // Promotions (placeholder for future implementation)
    /*
    |--------------------------------------------------------------------------
    | Promotions
    |--------------------------------------------------------------------------
    */

    Route::prefix('promotions')->group(function () {
        // Validation & Application
        Route::post('validate', [PromotionController::class, 'validatePromotion']);
        Route::post('apply', [PromotionController::class, 'applyPromotion']);

        // Queries
        Route::get('active', [PromotionController::class, 'active']);
        Route::post('best-applicable', [PromotionController::class, 'bestApplicable']);
        Route::get('statistics', [PromotionController::class, 'statistics']);
        Route::post('bulk-assign', [PromotionController::class, 'bulkAssignScopes']);

        // Operations on specific promotion
        Route::prefix('{id}')->group(function () {
            Route::post('duplicate', [PromotionController::class, 'duplicate']);
            Route::put('toggle-status', [PromotionController::class, 'toggleStatus']);
            Route::post('restore', [PromotionController::class, 'restore']);
            Route::delete('force', [PromotionController::class, 'forceDelete']);
        });
        // Core CRUD (using apiResource pattern)
    });
    Route::apiResource('promotions', PromotionController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    // Price Engine Routes
    Route::post('cart/calculate', [CartCalculationController::class, 'calculate']);
    Route::post('price/simulate', [PriceSimulationController::class, 'simulate']);

    // Inventory Conditions
    Route::delete('inventory-conditions/{condition}/force', [InventoryConditionController::class, 'forceDelete']);
    Route::post('inventory-conditions/{condition}/restore', [InventoryConditionController::class, 'restore']);
    Route::apiResource('inventory-conditions', InventoryConditionController::class);

    /* =========================
     | STOCK REPORTS
     ========================= */
    Route::prefix('stock-reports')->group(function () {
        Route::get('status', [StockReportController::class, 'stockStatus']);
        Route::get('movements', [StockReportController::class, 'stockMovements']);
        Route::get('aging', [StockReportController::class, 'stockAging']);
        Route::get('low-stock', [StockReportController::class, 'lowStock']);
    });
});
