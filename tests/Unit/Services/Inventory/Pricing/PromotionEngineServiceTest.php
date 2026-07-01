<?php

use App\Models\Core\Organization;
use App\Models\Inventory\Promotion;
use App\Services\Inventory\Pricing\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->engine = app(PromotionEngineService::class);
});

test('applies percentage discount correctly', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'stackable' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(100.00);
});

test('applies fixed discount correctly', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 50,
        'is_active' => true,
        'stackable' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(50.00);
});

test('respects min_order_amount threshold', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 50,
        'is_active' => true,
        'stackable' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => 500.00,
    ]);

    $discount = $this->engine->apply(collect(), 100.00, $this->organization->id);
    expect($discount)->toBe(0.00);

    $discount = $this->engine->apply(collect(), 500.00, $this->organization->id);
    expect($discount)->toBe(50.00);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);
    expect($discount)->toBe(50.00);
});

test('stops at first non-stackable promotion', function () {
    // Higher priority (2) processed first — it's non-stackable, so it stops after applying 30
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 30,
        'is_active' => true,
        'stackable' => false,
        'priority' => 2,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);
    // Lower priority (1) — never reached because non-stackable stopped
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 20,
        'is_active' => true,
        'stackable' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(30.00);
});

test('stackable promotions accumulate', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 30,
        'is_active' => true,
        'stackable' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 20,
        'is_active' => true,
        'stackable' => true,
        'priority' => 2,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(50.00);
});

test('ignores inactive promotions', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 50,
        'is_active' => false,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(0.00);
});

test('ignores promotions outside date range', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 50,
        'is_active' => true,
        'priority' => 1,
        'start_date' => now()->subDays(10),
        'end_date' => now()->subDay(),
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(0.00);
});

test('returns zero discount when no promotions apply', function () {
    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(0.00);
});

test('respects priority ordering', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 10,
        'is_active' => true,
        'stackable' => false,
        'priority' => 5,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'fixed',
        'value' => 50,
        'is_active' => true,
        'stackable' => false,
        'priority' => 10,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 1000.00, $this->organization->id);

    expect($discount)->toBe(50.00);
});

test('handles zero subtotal', function () {
    Promotion::factory()->create([
        'organization_id' => $this->organization->id,
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'priority' => 1,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'min_order_amount' => null,
    ]);

    $discount = $this->engine->apply(collect(), 0.00, $this->organization->id);

    expect($discount)->toBe(0.00);
});
