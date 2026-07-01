<?php

namespace App\Observers;

use App\Models\Auth\UserAssignment;
use App\Models\Core\Warehouse;
use App\Models\Inventory\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseObserver
{
    public function created(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse) {
            $location = new StockLocation([
                'organization_id' => $warehouse->organization_id,
                'name' => "{$warehouse->name} Storage",
                'type' => 'storage',
                'is_active' => true,
            ]);

            $location->locatable()->associate($warehouse);
            $location->save();
        });

        Log::info('StockLocation created for warehouse', [
            'warehouse_id' => $warehouse->id,
            'org_id' => $warehouse->organization_id,
        ]);
    }

    public function updated(Warehouse $warehouse): void
    {
        $location = StockLocation::where([
            'locatable_type' => $warehouse->getMorphClass(),
            'locatable_id' => $warehouse->id,
        ])->first();

        if (! $location) {
            return;
        }

        $dirty = false;

        if ($warehouse->wasChanged('name')) {
            $location->name = $warehouse->name;
            $dirty = true;
        }

        if ($dirty) {
            $location->save();
        }
    }

    public function deleted(Warehouse $warehouse): void
    {
        StockLocation::where([
            'locatable_type' => $warehouse->getMorphClass(),
            'locatable_id' => $warehouse->id,
        ])->update([
            'is_active' => false,
        ]);

        UserAssignment::where('assignable_type', $warehouse->getMorphClass())
            ->where('assignable_id', $warehouse->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    public function restored(Warehouse $warehouse): void
    {
        StockLocation::where([
            'locatable_type' => $warehouse->getMorphClass(),
            'locatable_id' => $warehouse->id,
        ])->update([
            'is_active' => true,
        ]);

        UserAssignment::where('assignable_type', $warehouse->getMorphClass())
            ->where('assignable_id', $warehouse->id)
            ->whereNotNull('ended_at')
            ->update(['ended_at' => null]);
    }
}
