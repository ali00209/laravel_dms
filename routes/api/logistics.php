<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\api\Logistics\DeliveryController;
use App\Http\Controllers\v1\api\Logistics\ReverseLogisticsController;

Route::middleware(['auth:sanctum', 'ability:inventory.*'])->prefix('logistics')->group(function () {
    Route::post('/deliveries/assign-rider', [DeliveryController::class, 'assignRider']);
    Route::post('/deliveries/{id}/deliver', [DeliveryController::class, 'deliver']);
    Route::get('/riders/{riderId}/stock', [DeliveryController::class, 'riderStock']);
    Route::post('/returns', [ReverseLogisticsController::class, 'initiateReturn']);
});
