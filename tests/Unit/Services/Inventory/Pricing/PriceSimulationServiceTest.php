<?php

use App\Models\Core\Organization;
use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\Unit;
use App\Models\User;
use App\Services\Inventory\Pricing\PriceSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
    $this->category = Category::factory()->create(['organization_id' => $this->organization->id]);
    $this->brand = Brand::factory()->create(['organization_id' => $this->organization->id]);
    $this->product = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'category_id' => $this->category->id,
        'brand_id' => $this->brand->id,
    ]);
    $this->variant = ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id' => $this->product->id,
        'sale_price' => 100.00,
    ]);

    $this->context = (object) [
        'organization_id' => $this->organization->id,
        'branch_id' => null,
    ];
    $this->service = app(PriceSimulationService::class);
});

test('simulates price for a given variant and quantity', function () {
    $result = $this->service->simulate($this->context, [
        'product_variant_id' => $this->variant->id,
        'quantity' => 5,
    ]);

    expect($result)->toHaveKeys(['product_variant_id', 'base_price', 'quantity', 'subtotal']);
    expect($result['product_variant_id'])->toBe($this->variant->id);
    expect($result['base_price'])->toBe(100.00);
    expect($result['quantity'])->toBe(5);
    expect($result['subtotal'])->toBe(500.00);
});

test('handles non-existent variant', function () {
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $this->service->simulate($this->context, [
        'product_variant_id' => 99999,
        'quantity' => 1,
    ]);
});

test('handles single quantity', function () {
    $result = $this->service->simulate($this->context, [
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);

    expect($result['subtotal'])->toBe(100.00);
});
