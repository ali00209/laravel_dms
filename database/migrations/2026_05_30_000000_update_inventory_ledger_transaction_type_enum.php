<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE inventory_ledger MODIFY COLUMN transaction_type ENUM(
            'opening', 'purchase', 'sale', 'transfer_in', 'transfer_out',
            'adjustment_in', 'adjustment_out', 'sales_return', 'purchase_return',
            'transfer_dispatch', 'transfer_in_transit', 'transfer_received',
            'rider_assigned', 'delivered', 'reverse_pickup',
            'damage_out', 'damage_in', 'system', 'expired',
            'allocated', 'unallocated'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE inventory_ledger MODIFY COLUMN transaction_type ENUM(
            'opening', 'purchase', 'sale', 'transfer_in', 'transfer_out',
            'adjustment_in', 'adjustment_out', 'sales_return', 'purchase_return'
        ) NOT NULL");
    }
};
