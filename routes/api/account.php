<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\api\Account\AccountController;
use App\Http\Controllers\v1\api\Account\JournalController;
use App\Http\Controllers\v1\api\Voucher\PaymentController;
use App\Http\Controllers\v1\api\Voucher\ReceiptController;

Route::prefix('accounts')->middleware(['ability:accounts.*'])->group(function () {
    // Standard CRUD routes
    Route::get('/select-list', [AccountController::class, 'selectList'])->name('accounts.select-list');
    Route::get('/types', [AccountController::class, 'getTypes'])->name('accounts.types');
    Route::get('/tree', [AccountController::class, 'tree'])->name('accounts.tree');
    Route::get('/chart', [AccountController::class, 'chart'])->name('accounts.chart');
    Route::get('/trial-balance', [AccountController::class, 'trialBalance'])->name('accounts.trial-balance');
    Route::get('/profit-and-loss', [AccountController::class, 'profitAndLoss'])->name('accounts.profit-and-loss');
    Route::get('/balance-sheet', [AccountController::class, 'balanceSheet'])->name('accounts.balance-sheet');
    Route::post('/bulk-update', [AccountController::class, 'bulkUpdate'])->name('accounts.bulk-update');
    Route::post('/export', [AccountController::class, 'export'])->name('accounts.export');
    Route::post('/import', [AccountController::class, 'import'])->name('accounts.import');
    Route::patch('/{id}/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');
    Route::get('/{id}/balance', [AccountController::class, 'getBalance'])->name('accounts.balance');
    Route::get('/{id}/hierarchy', [AccountController::class, 'hierarchy'])->name('accounts.hierarchy');
    Route::get('/{id}/summary', [AccountController::class, 'summary'])->name('accounts.summary');

    // Soft delete operations (admin only)
    Route::post('/{id}/restore', [AccountController::class, 'restore'])->name('accounts.restore');
    Route::delete('/{id}/force', [AccountController::class, 'forceDelete'])->name('accounts.force-delete');

    // Individual account routes
    Route::get('/', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/{id}', [AccountController::class, 'show'])->name('accounts.show');
    Route::put('/{id}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/{id}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Journal Entry Routes
    Route::prefix('journals')->group(function () {
        Route::get('/', [JournalController::class, 'index'])->name('journals.index');
        Route::post('/', [JournalController::class, 'store'])->name('journals.store');
        Route::get('{id}', [JournalController::class, 'show'])->name('journals.show');
        Route::post('{id}/post', [JournalController::class, 'post'])->name('journals.post');
        Route::post('{id}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');
    });

    // Payment Voucher Routes
    Route::prefix('payments')->group(function () {
        Route::post('{id}/post', [PaymentController::class, 'post'])->name('payments.post');
        Route::post('{id}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
        Route::post('{id}/allocate', [PaymentController::class, 'allocate'])->name('payments.allocate');
        Route::post('{id}/restore', [PaymentController::class, 'restore'])->name('payments.restore');
        Route::delete('{id}/force', [PaymentController::class, 'forceDelete'])->name('payments.force-delete');

        Route::get('/', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('{id}', [PaymentController::class, 'show'])->name('payments.show');
        Route::put('{id}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('{id}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    });

    // Expense Claim Routes
    Route::prefix('expense-claims')->group(function () {
        Route::post('{id}/post', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'post'])->name('expense-claims.post');
        Route::post('{id}/cancel', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'cancel'])->name('expense-claims.cancel');
        Route::post('{id}/restore', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'restore'])->name('expense-claims.restore');
        Route::delete('{id}/force', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'forceDelete'])->name('expense-claims.force-delete');

        Route::get('/', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'index'])->name('expense-claims.index');
        Route::post('/', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'store'])->name('expense-claims.store');
        Route::get('{id}', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'show'])->name('expense-claims.show');
        Route::put('{id}', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'update'])->name('expense-claims.update');
        Route::delete('{id}', [\App\Http\Controllers\v1\api\Voucher\ExpenseClaimController::class, 'destroy'])->name('expense-claims.destroy');

    });

    // Receipt Voucher Routes
    Route::prefix('receipts')->group(function () {
        Route::post('{id}/post', [ReceiptController::class, 'post'])->name('receipts.post');
        Route::post('{id}/cancel', [ReceiptController::class, 'cancel'])->name('receipts.cancel');
        Route::post('{id}/allocate', [ReceiptController::class, 'allocate'])->name('receipts.allocate');
        Route::post('{id}/restore', [ReceiptController::class, 'restore'])->name('receipts.restore');
        Route::delete('{id}/force', [ReceiptController::class, 'forceDelete'])->name('receipts.force-delete');

        Route::get('/', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::post('/', [ReceiptController::class, 'store'])->name('receipts.store');
        Route::get('{id}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::put('{id}', [ReceiptController::class, 'update'])->name('receipts.update');
        Route::delete('{id}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
    });

});
