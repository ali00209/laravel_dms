<?php

namespace App\Observers;

use App\Models\Auth\UserAssignment;
use App\Models\Core\Branch;
use App\Models\Inventory\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchObserver
{
    public function created(Branch $branch): void
    {
        DB::transaction(function () use ($branch) {
            $location = new StockLocation([
                'organization_id' => $branch->organization_id,
                'name' => "{$branch->name} Storage",
                'type' => 'storage',
                'is_active' => true,
            ]);

            $location->locatable()->associate($branch);
            $location->save();
        });

        Log::info('StockLocation created for branch', [
            'branch_id' => $branch->id,
            'org_id' => $branch->organization_id,
        ]);
    }

    public function updated(Branch $branch): void
    {
        $location = StockLocation::where([
            'locatable_type' => $branch->getMorphClass(),
            'locatable_id' => $branch->id,
        ])->first();

        if (! $location) {
            return;
        }

        $dirty = false;

        if ($branch->wasChanged('name')) {
            $location->name = $branch->name;
            $dirty = true;
        }

        if ($dirty) {
            $location->save();
        }
    }

    public function deleted(Branch $branch): void
    {
        StockLocation::where([
            'locatable_type' => $branch->getMorphClass(),
            'locatable_id' => $branch->id,
        ])->update([
            'is_active' => false,
        ]);

        UserAssignment::where('assignable_type', $branch->getMorphClass())
            ->where('assignable_id', $branch->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    public function restored(Branch $branch): void
    {
        StockLocation::where([
            'locatable_type' => $branch->getMorphClass(),
            'locatable_id' => $branch->id,
        ])->update([
            'is_active' => true,
        ]);

        UserAssignment::where('assignable_type', $branch->getMorphClass())
            ->where('assignable_id', $branch->id)
            ->whereNotNull('ended_at')
            ->update(['ended_at' => null]);
    }
}
