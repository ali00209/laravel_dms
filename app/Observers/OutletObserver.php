<?php

namespace App\Observers;

use App\Models\Auth\UserAssignment;
use App\Models\Core\Outlet;

class OutletObserver
{
    public function deleted(Outlet $outlet): void
    {
        UserAssignment::where('assignable_type', $outlet->getMorphClass())
            ->where('assignable_id', $outlet->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    public function restored(Outlet $outlet): void
    {
        UserAssignment::where('assignable_type', $outlet->getMorphClass())
            ->where('assignable_id', $outlet->id)
            ->whereNotNull('ended_at')
            ->update(['ended_at' => null]);
    }
}
