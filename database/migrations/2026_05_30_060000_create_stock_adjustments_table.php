<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('stock_location_id')->constrained('stock_locations');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches');
            $table->enum('adjustment_type', ['addition', 'reduction']);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('reason')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
