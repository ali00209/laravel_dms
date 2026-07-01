<?php

use App\Models\Core\Organization;
use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\PriceList;
use App\Models\Inventory\PriceListItem;
use App\Models\Inventory\Unit;
use App\Models\User;
use App\Services\Inventory\Pricing\PriceResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
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

    $this->resolver = app(PriceResolverService::class);
    $this->context = (object) [
        'organization_id' => $this->organization->id,
        'branch_id' => null,
    ];
});

test('resolves sale_price when no price list exists', function () {
    $price = $this->resolver->resolve($this->variant, $this->context);

    expect($price)->toBe(100.00);
});

test('resolves price from highest-priority active price list', function () {
    $priceList = PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => true,
        'priority' => 10,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $this->variant->id,
        'price' => 80.00,
    ]);

    $price = $this->resolver->resolve($this->variant, $this->context);

    expect($price)->toBe(80.00);
});

test('ignores lower priority price list when higher priority exists', function () {
    $low = PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => true,
        'priority' => 5,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $low->id,
        'product_variant_id' => $this->variant->id,
        'price' => 90.00,
    ]);

    $high = PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => true,
        'priority' => 10,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $high->id,
        'product_variant_id' => $this->variant->id,
        'price' => 80.00,
    ]);

    $price = $this->resolver->resolve($this->variant, $this->context);

    expect($price)->toBe(80.00);
});

test('ignores inactive price lists', function () {
    $priceList = PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => false,
        'priority' => 10,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $this->variant->id,
        'price' => 80.00,
    ]);

    $price = $this->resolver->resolve($this->variant, $this->context);

    expect($price)->toBe(100.00);
});

test('price list with no matching item falls back to sale_price', function () {
    PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => true,
        'priority' => 10,
    ]);

    $price = $this->resolver->resolve($this->variant, $this->context);

    expect($price)->toBe(100.00);
});

test('caches resolved prices', function () {
    $priceList = PriceList::factory()->create([
        'organization_id' => $this->organization->id,
        'is_active' => true,
        'priority' => 10,
    ]);
    PriceListItem::factory()->create([
        'price_list_id' => $priceList->id,
        'product_variant_id' => $this->variant->id,
        'price' => 80.00,
    ]);

    $price1 = $this->resolver->resolve($this->variant, $this->context);
    expect($price1)->toBe(80.00);

    $price2 = $this->resolver->resolve($this->variant, $this->context);
    expect($price2)->toBe(80.00);
});
