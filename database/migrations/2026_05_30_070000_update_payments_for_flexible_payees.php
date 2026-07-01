<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->nullableMorphs('payable');
            $table->string('description')->nullable()->after('reference_number');
            $table->string('payment_type', 50)->nullable()->after('reference_number');
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['expense_account_id']);
            $table->dropColumn(['payable_type', 'payable_id', 'description', 'payment_type', 'expense_account_id']);
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }
};
