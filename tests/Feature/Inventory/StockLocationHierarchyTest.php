<?php

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\Warehouse;
use App\Models\Inventory\StockLocation;
use Faker\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('auto-creates StockLocation when Organization is created', function () {
    Queue::fake();

    $org = Organization::factory()->create();

    $location = StockLocation::where('locatable_type', $org->getMorphClass())
        ->where('locatable_id', $org->id)
        ->first();

    expect($location)->not->toBeNull();
    expect($location->name)->toBe("{$org->name} Central");
    expect($location->is_active)->toBeTrue();
});

it('auto-creates StockLocation when Branch is created', function () {
    $org = Organization::factory()->create();
    $branch = Branch::factory()->create(['organization_id' => $org->id]);

    $location = StockLocation::where('locatable_type', $branch->getMorphClass())
        ->where('locatable_id', $branch->id)
        ->first();

    expect($location)->not->toBeNull();
    expect($location->name)->toBe("{$branch->name} Storage");
    expect($location->is_active)->toBeTrue();
});

it('auto-creates StockLocation when Warehouse is created', function () {
    $org = Organization::factory()->create();
    $branch = Branch::factory()->create(['organization_id' => $org->id]);
    $warehouse = Warehouse::factory()->create([
        'organization_id' => $org->id,
        'branch_id' => $branch->id,
    ]);

    $location = StockLocation::where('locatable_type', $warehouse->getMorphClass())
        ->where('locatable_id', $warehouse->id)
        ->first();

    expect($location)->not->toBeNull();
    expect($location->name)->toBe("{$warehouse->name} Storage");
    expect($location->is_active)->toBeTrue();
});
