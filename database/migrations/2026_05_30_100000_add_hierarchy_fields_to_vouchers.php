<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // === CREDIT NOTES (sale returns) — add full hierarchy ===
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === DEBIT NOTES (purchase returns) — add full hierarchy ===
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === DELIVERY NOTES — add full hierarchy ===
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === RECEIPT NOTES — add full hierarchy ===
        Schema::table('receipt_notes', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('organization_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === INVOICES — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === PURCHASE BILLS — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === PURCHASE ORDERS — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === RECEIPTS — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === PAYMENTS — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === EXPENSE CLAIMS — add warehouse_id + outlet_id (branch_id already exists) ===
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('warehouse_id')
                ->constrained('outlets')->nullOnDelete();
        });

        // === INVENTORY BATCHES — add organization_id for direct tenant scoping ===
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tables = [
            'credit_notes', 'debit_notes', 'delivery_notes', 'receipt_notes',
            'invoices', 'purchase_bills', 'purchase_orders',
            'receipts', 'payments', 'expense_claims',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropConstrainedForeignId('outlet_id');
                $t->dropConstrainedForeignId('warehouse_id');
                if (in_array($table, ['credit_notes', 'debit_notes', 'delivery_notes', 'receipt_notes'])) {
                    $t->dropConstrainedForeignId('branch_id');
                }
            });
        }

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
