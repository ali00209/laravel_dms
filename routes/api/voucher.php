<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\api\Voucher\CreditNoteController;
use App\Http\Controllers\v1\api\Voucher\DebitNoteController;
use App\Http\Controllers\v1\api\Voucher\DeliveryNoteController;
use App\Http\Controllers\v1\api\Voucher\InvoiceController;
use App\Http\Controllers\v1\api\Voucher\PurchaseBillController;
use App\Http\Controllers\v1\api\Voucher\PurchaseOrderController;
use App\Http\Controllers\v1\api\Voucher\ReceiptNoteController;
use App\Http\Controllers\v1\api\Voucher\AttachmentController;
use App\Http\Controllers\v1\api\Voucher\SaleOrderController;
use App\Http\Controllers\v1\api\Voucher\TransferOrderController;

Route::middleware(["ability:voucher.*"])->group(function () {
    // Sale Order Routes
    Route::prefix("sale-orders")->group(function () {
        // Workflow routes
        Route::post("/{id}/submit", [SaleOrderController::class, "submit"]);
        Route::post("/{id}/review", [SaleOrderController::class, "review"]);
        Route::post("/{id}/approve", [SaleOrderController::class, "approve"]);
        Route::post("/{id}/reject", [SaleOrderController::class, "reject"]);
        Route::post("/{id}/confirm", [SaleOrderController::class, "confirm"]);
        Route::post("/{id}/cancel", [SaleOrderController::class, "cancel"]);
        Route::post("/{id}/restore", [SaleOrderController::class, "restore"]);
        Route::delete("/{id}/force", [
            SaleOrderController::class,
            "forceDelete",
        ]);

        Route::get("/", [SaleOrderController::class, "index"]);
        Route::post("/", [SaleOrderController::class, "store"]);
        Route::get("/{id}", [SaleOrderController::class, "show"]);
        Route::put("/{id}", [SaleOrderController::class, "update"]);
        Route::delete("/{id}", [SaleOrderController::class, "destroy"]);
    });
    // Invoice Routes
    Route::prefix("invoices")->group(function () {
        Route::post("/{id}/submit", [InvoiceController::class, "submit"]);
        Route::post("/{id}/review", [InvoiceController::class, "review"]);
        Route::post("/{id}/approve", [InvoiceController::class, "approve"]);
        Route::post("/{id}/confirm", [InvoiceController::class, "confirm"]);
        Route::post("/{id}/reject", [InvoiceController::class, "reject"]);
        Route::post("/{id}/cancel", [InvoiceController::class, "cancel"]);
        Route::post("{id}/restore", [InvoiceController::class, "restore"]);
        Route::delete("{id}/force", [InvoiceController::class, "forceDelete"]);

        Route::get("/", [InvoiceController::class, "index"]);
        Route::post("/", [InvoiceController::class, "store"]);
        Route::get("{id}", [InvoiceController::class, "show"]);
        Route::put("{id}", [InvoiceController::class, "update"]);
        Route::delete("{id}", [InvoiceController::class, "destroy"]);
    });
    // Goods Delivery Note Routes
    Route::prefix("delivery-notes")->group(function () {
        Route::post("{id}/submit", [DeliveryNoteController::class, "submit"]);
        Route::post("{id}/review", [DeliveryNoteController::class, "review"]);
        Route::post("{id}/approve", [DeliveryNoteController::class, "approve"]);
        Route::post("{id}/reject", [DeliveryNoteController::class, "reject"]);
        Route::post("{id}/cancel", [DeliveryNoteController::class, "cancel"]);
        Route::post("{id}/restore", [DeliveryNoteController::class, "restore"]);
        Route::delete("{id}/force", [
            DeliveryNoteController::class,
            "forceDelete",
        ]);

        Route::get("/", [DeliveryNoteController::class, "index"]);
        Route::post("/", [DeliveryNoteController::class, "store"]);
        Route::get("{id}", [DeliveryNoteController::class, "show"]);
        Route::put("{id}", [DeliveryNoteController::class, "update"]);
        Route::delete("{id}", [DeliveryNoteController::class, "destroy"]);
    });
    // Credit Note (Sale Return) Routes
    Route::prefix("credit-notes")->group(function () {
        Route::post("{id}/restore", [CreditNoteController::class, "restore"]);
        Route::delete("{id}/force", [
            CreditNoteController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [CreditNoteController::class, "submit"]);
        Route::post("{id}/review", [CreditNoteController::class, "review"]);
        Route::post("{id}/approve", [CreditNoteController::class, "approve"]);
        Route::post("{id}/reject", [CreditNoteController::class, "reject"]);
        Route::post("{id}/cancel", [CreditNoteController::class, "cancel"]);

        Route::get("/", [CreditNoteController::class, "index"]);
        Route::post("/", [CreditNoteController::class, "store"]);
        Route::get("{id}", [CreditNoteController::class, "show"]);
        Route::put("{id}", [CreditNoteController::class, "update"]);
        Route::delete("{id}", [CreditNoteController::class, "destroy"]);
    });
    // Purchase Order Routes
    Route::prefix("purchase-orders")->group(function () {
        Route::post("{id}/restore", [
            PurchaseOrderController::class,
            "restore",
        ]);
        Route::delete("{id}/force", [
            PurchaseOrderController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [PurchaseOrderController::class, "submit"]);
        Route::post("{id}/review", [PurchaseOrderController::class, "review"]);
        Route::post("{id}/approve", [
            PurchaseOrderController::class,
            "approve",
        ]);
        Route::post("{id}/reject", [PurchaseOrderController::class, "reject"]);
        Route::post("{id}/cancel", [PurchaseOrderController::class, "cancel"]);

        Route::get("/", [PurchaseOrderController::class, "index"]);
        Route::post("/", [PurchaseOrderController::class, "store"]);
        Route::get("{id}", [PurchaseOrderController::class, "show"]);
        Route::put("{id}", [PurchaseOrderController::class, "update"]);
        Route::delete("{id}", [PurchaseOrderController::class, "destroy"]);
    });
    // Purchase Bill Routes
    Route::prefix("purchase-bills")->group(function () {
        Route::post("{id}/restore", [PurchaseBillController::class, "restore"]);
        Route::delete("{id}/force", [
            PurchaseBillController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [PurchaseBillController::class, "submit"]);
        Route::post("{id}/review", [PurchaseBillController::class, "review"]);
        Route::post("{id}/approve", [PurchaseBillController::class, "approve"]);
        Route::post("{id}/reject", [PurchaseBillController::class, "reject"]);
        Route::post("{id}/cancel", [PurchaseBillController::class, "cancel"]);

        Route::get("/", [PurchaseBillController::class, "index"]);
        Route::post("/", [PurchaseBillController::class, "store"]);
        Route::get("{id}", [PurchaseBillController::class, "show"]);
        Route::put("{id}", [PurchaseBillController::class, "update"]);
        Route::delete("{id}", [PurchaseBillController::class, "destroy"]);
    });
    // Goods Receipt Note Routes
    Route::prefix("receipt-notes")->group(function () {
        Route::post("{id}/restore", [ReceiptNoteController::class, "restore"]);
        Route::delete("{id}/force", [
            ReceiptNoteController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [ReceiptNoteController::class, "submit"]);
        Route::post("{id}/review", [ReceiptNoteController::class, "review"]);
        Route::post("{id}/approve", [ReceiptNoteController::class, "approve"]);
        Route::post("{id}/reject", [ReceiptNoteController::class, "reject"]);
        Route::post("{id}/cancel", [ReceiptNoteController::class, "cancel"]);

        Route::get("/", [ReceiptNoteController::class, "index"]);
        Route::post("/", [ReceiptNoteController::class, "store"]);
        Route::get("{id}", [ReceiptNoteController::class, "show"]);
        Route::put("{id}", [ReceiptNoteController::class, "update"]);
        Route::delete("{id}", [ReceiptNoteController::class, "destroy"]);
    });
    // Debit Note (Purchase return) Routes
    Route::prefix("debit-notes")->group(function () {
        Route::post("{id}/restore", [DebitNoteController::class, "restore"]);
        Route::delete("{id}/force", [
            DebitNoteController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [DebitNoteController::class, "submit"]);
        Route::post("{id}/review", [DebitNoteController::class, "review"]);
        Route::post("{id}/approve", [DebitNoteController::class, "approve"]);
        Route::post("{id}/reject", [DebitNoteController::class, "reject"]);
        Route::post("{id}/cancel", [DebitNoteController::class, "cancel"]);

        Route::get("/", [DebitNoteController::class, "index"]);
        Route::post("/", [DebitNoteController::class, "store"]);
        Route::get("{id}", [DebitNoteController::class, "show"]);
        Route::put("{id}", [DebitNoteController::class, "update"]);
        Route::delete("{id}", [DebitNoteController::class, "destroy"]);
    });
    // Transfer Order Routes
    Route::prefix("transfer-orders")->group(function () {
        Route::post("{id}/restore", [
            TransferOrderController::class,
            "restore",
        ]);
        Route::delete("{id}/force", [
            TransferOrderController::class,
            "forceDelete",
        ]);
        Route::post("{id}/submit", [TransferOrderController::class, "submit"]);
        Route::post("{id}/review", [TransferOrderController::class, "review"]);
        Route::post("{id}/approve", [
            TransferOrderController::class,
            "approve",
        ]);
        Route::post("{id}/reject", [TransferOrderController::class, "reject"]);
        Route::post("{id}/cancel", [TransferOrderController::class, "cancel"]);
        Route::post("{id}/dispatch", [
            TransferOrderController::class,
            "dispatch",
        ]);
        Route::post("{id}/receive", [
            TransferOrderController::class,
            "receive",
        ]);

        Route::get("/", [TransferOrderController::class, "index"]);
        Route::post("/", [TransferOrderController::class, "store"]);
        Route::get("{id}", [TransferOrderController::class, "show"]);
        Route::put("{id}", [TransferOrderController::class, "update"]);
        Route::delete("{id}", [TransferOrderController::class, "destroy"]);
    });
    // Polymorphic attachments — works for all document types (vouchers, journals, etc.)
    Route::prefix("attachments")->group(function () {
        Route::post("/upload", [AttachmentController::class, "upload"]);
        Route::get("/{id}/download", [AttachmentController::class, "download"]);

        Route::get("/", [AttachmentController::class, "index"]);
        Route::get("/{id}", [AttachmentController::class, "show"]);
        Route::delete("/{id}", [AttachmentController::class, "destroy"]);
    });
});
