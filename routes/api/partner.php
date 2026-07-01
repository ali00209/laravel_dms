<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\api\Partner\CustomerController;
use App\Http\Controllers\v1\api\Partner\PartnerCategoryController;
use App\Http\Controllers\v1\api\Partner\SupplierController;

Route::middleware(['ability:partner.*'])->group(function () {
    Route::get('customer_categories', [PartnerCategoryController::class, 'index'])->defaults('category_type', 'customer');
    Route::get('supplier_categories', [PartnerCategoryController::class, 'index'])->defaults('category_type', 'supplier');
    Route::post('partner_categories/{partner_category}/restore', [PartnerCategoryController::class, 'restore']);
    Route::delete('partner_categories/{partner_category}/force', [PartnerCategoryController::class, 'forceDelete']);
    Route::apiResource('partner_categories', PartnerCategoryController::class)->except(['index']);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);
});
