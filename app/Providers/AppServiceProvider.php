<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Core\Branch;
use App\Models\Core\Outlet;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Coupon;
use App\Models\Partner\Customer;
use App\Models\Inventory\Product;
use App\Models\Core\Organization;
use App\Models\Core\OutletSection;
use App\Models\Inventory\Category;
use App\Models\Vouchers\CashRegister;
use App\Models\Vouchers\SaleOrder;
use App\Models\Vouchers\Invoice;
use App\Models\Vouchers\DeliveryNote;
use App\Models\Vouchers\CreditNote;
use App\Models\Vouchers\PurchaseOrder;
use App\Models\Vouchers\PurchaseBill;
use App\Models\Vouchers\ReceiptNote;
use App\Models\Vouchers\DebitNote;
use App\Models\Vouchers\Payment;
use App\Models\Vouchers\Receipt;
use App\Models\Vouchers\ExpenseClaim;
use App\Models\Accounts\Journal;
use App\Models\Core\Employee;
use Illuminate\Support\Facades\Event;
use App\Models\Core\WarehouseSection;
use App\Models\Inventory\CouponScope;
use App\Models\Inventory\CouponTarget;
use Illuminate\Support\ServiceProvider;
use App\Observers\OrganizationObserver;
use App\Observers\OutletObserver;
use App\Models\Inventory\ProductVariant;
use App\Observers\OutletSectionObserver;
use App\Observers\WarehouseSectionObserver;
use App\Observers\BranchObserver;
use App\Observers\WarehouseObserver;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * ------------------------------------------------------------------
     * MORPH MAP
     * ------------------------------------------------------------------
     *
     * IMPORTANT:
     * - Keys are persisted in DB.
     * - NEVER rename once in production.
     */
    protected const MORPH_MAP = [
        'organization'      => Organization::class,
        'branch'            => Branch::class,
        'warehouse'         => Warehouse::class,
        'outlet'            => Outlet::class,
        'warehouse_section' => WarehouseSection::class,
        'outlet_section'    => OutletSection::class,
        'cash_register'     => CashRegister::class,
        'user'              => User::class,
        'product'           => Product::class,
        'variant'           => ProductVariant::class,
        'coupon'            => Coupon::class,
        'coupon_scope'      => CouponScope::class,
        'coupon_target'     => CouponTarget::class,
        'customer'          => Customer::class,
        'category'          => Category::class,
        'sale_order'        => SaleOrder::class,
        'invoice'           => Invoice::class,
        'delivery_note'     => DeliveryNote::class,
        'credit_note'       => CreditNote::class,
        'purchase_order'    => PurchaseOrder::class,
        'purchase_bill'     => PurchaseBill::class,
        'receipt_note'      => ReceiptNote::class,
        'debit_note'        => DebitNote::class,
        'payment'           => Payment::class,
        'receipt'           => Receipt::class,
        'journal'           => Journal::class,
        'expense_claim'     => ExpenseClaim::class,
        'employee'          => Employee::class,
    ];

    /**
     * ------------------------------------------------------------------
     * REGISTER
     * ------------------------------------------------------------------
     */
    public function register(): void
    {
        // $this->app->bind(
        // );
    }

    /**
     * ------------------------------------------------------------------
     * BOOT
     * ------------------------------------------------------------------
     */
    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerObservers();
        $this->registerEventListeners();
    }

    /**
     * ------------------------------------------------------------------
     * Morph Map Registration (STRICT MODE)
     * ------------------------------------------------------------------
     *
     * enforceMorphMap() guarantees:
     * - Only aliases are stored in DB
     * - Full class names are rejected
     * - Cleaner, shorter polymorphic values
     */
    protected function registerMorphMap(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);
    }

    /**
     * ------------------------------------------------------------------
     * Model Observers
     * ------------------------------------------------------------------
     *
     * Used for:
     * - Auto stock location creation
     * - Metadata sync
     * - Soft-disable cascading logic
     */
    protected function registerObservers(): void
    {
        Organization::observe(OrganizationObserver::class);
        Branch::observe(BranchObserver::class);
        Warehouse::observe(WarehouseObserver::class);
        WarehouseSection::observe(WarehouseSectionObserver::class);
        Outlet::observe(OutletObserver::class);
        OutletSection::observe(OutletSectionObserver::class);
    }

    /**
     * ------------------------------------------------------------------
     * Event Listeners
     * ------------------------------------------------------------------
     *
     * Business workflow events
     */
    protected function registerEventListeners(): void
    {
        $listeners = [
            \App\Events\Voucher\VoucherPosted::class    => [\App\Listeners\HandleVoucherPosted::class],
            \App\Events\Voucher\VoucherApproved::class  => [\App\Listeners\HandleVoucherApproved::class],
            \App\Events\Voucher\VoucherUpdated::class   => [\App\Listeners\HandleVoucherUpdated::class],
            \App\Events\Voucher\VoucherCancelled::class => [\App\Listeners\HandleVoucherCancelled::class],

            \App\Events\Voucher\CreditNotePosted::class   => [\App\Listeners\HandleCreditNotePosted::class],
            \App\Events\Voucher\DebitNotePosted::class    => [\App\Listeners\HandleDebitNotePosted::class],
            \App\Events\Voucher\DeliveryNotePosted::class => [\App\Listeners\HandleDeliveryNotePosted::class],
            \App\Events\Voucher\ReceiptNotePosted::class  => [\App\Listeners\HandleReceiptNotePosted::class],

            \App\Events\Voucher\PaymentPosted::class           => [\App\Listeners\HandlePaymentPosted::class],
            \App\Events\Voucher\ReceiptPosted::class           => [\App\Listeners\HandleReceiptPosted::class],
            \App\Events\Voucher\ExpenseClaimPosted::class      => [\App\Listeners\HandleExpenseClaimPosted::class],
        ];

        foreach ($listeners as $event => $handlers) {
            foreach ((array) $handlers as $handler) {
                Event::listen($event, $handler);
            }
        }
    }
}
