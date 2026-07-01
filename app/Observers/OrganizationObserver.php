<?php

namespace App\Observers;

use App\Jobs\SetupNewOrganization;
use App\Models\Auth\UserAssignment;
use App\Models\Core\Organization;
use App\Models\Inventory\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            $location = new StockLocation([
                'organization_id' => $organization->id,
                'name' => "{$organization->name} Central",
                'type' => 'storage',
                'is_active' => true,
            ]);

            $location->locatable()->associate($organization);
            $location->save();
        });

        Log::info('StockLocation created for organization', ['org_id' => $organization->id]);

        SetupNewOrganization::dispatch($organization);
    }

    public function deleted(Organization $organization): void
    {
        UserAssignment::where('assignable_type', $organization->getMorphClass())
            ->where('assignable_id', $organization->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    public function restored(Organization $organization): void
    {
        UserAssignment::where('assignable_type', $organization->getMorphClass())
            ->where('assignable_id', $organization->id)
            ->whereNotNull('ended_at')
            ->update(['ended_at' => null]);
    }
}
