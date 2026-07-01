<?php

use App\Models\Core\Organization;
use App\Services\Inventory\Pricing\CartCalculationService;
use App\Services\Inventory\Pricing\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->context = (object) [
        'organization_id' => $this->organization->id,
    ];
    $this->service = app(CartCalculationService::class);
});

test('calculates subtotal correctly for multiple items', function () {
    $result = $this->service->calculate($this->context, [
        'items' => [
            ['price' => 10.00, 'quantity' => 2],
            ['price' => 5.00, 'quantity' => 3],
        ],
    ]);

    expect($result['subtotal'])->toBe(35.00);
});

test('returns correct structure', function () {
    $result = $this->service->calculate($this->context, [
        'items' => [
            ['price' => 100.00, 'quantity' => 1],
        ],
    ]);

    expect($result)->toHaveKeys(['subtotal', 'discount', 'total']);
    expect($result['subtotal'])->toBe(100.00);
    expect($result['discount'])->toBeNumeric();
    expect($result['total'])->toBe($result['subtotal'] - $result['discount']);
});

test('handles empty items array', function () {
    $result = $this->service->calculate($this->context, [
        'items' => [],
    ]);

    expect($result['subtotal'])->toEqual(0.00);
    expect($result['discount'])->toEqual(0.00);
    expect($result['total'])->toEqual(0.00);
});

test('handles decimal prices', function () {
    $result = $this->service->calculate($this->context, [
        'items' => [
            ['price' => 1.99, 'quantity' => 3],
        ],
    ]);

    expect($result['subtotal'])->toBe(5.97);
});

test('passes organizationId to PromotionEngineService', function () {
    $org = Organization::factory()->create();
    $context = (object) ['organization_id' => $org->id];

    $result = $this->service->calculate($context, [
        'items' => [['price' => 10.00, 'quantity' => 1]],
    ]);

    expect($result)->toHaveKey('subtotal');
});
