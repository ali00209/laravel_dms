<?php

namespace App\Services\Auth;

use App\Models\Auth\UserAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Request;
use RuntimeException;

class UserAssignmentService
{
    /*
    |--------------------------------------------------------------------------
    | LISTING
    |--------------------------------------------------------------------------
    */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return UserAssignment::with(["role", "assignable"])
            // If user_id is provided, filter by it. If not, show all.
            ->when(isset($filters["user_id"]), function ($q) use ($filters) {
                return $q->where("user_id", $filters["user_id"]);
            })
            // If you need to filter by organization (Tenant view)
            ->when(isset($filters["organization_id"]), function ($q) use (
                $filters,
            ) {
                return $q->whereHasMorph("assignable", "*", function (
                    $query,
                ) use ($filters) {
                    $query->where(
                        "organization_id",
                        $filters["organization_id"],
                    );
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): ?UserAssignment
    {
        return UserAssignment::with(["role", "assignable"])->find($id);
    }

    public function getActiveAssignments(int $userId)
    {
        return UserAssignment::with(["role.permissions", "assignable"])
            ->where("user_id", $userId)
            // Optimization: Only get assignments that haven't ended
            ->where(function ($query) {
                $query->whereNull("ended_at")->orWhere("ended_at", ">", now());
            })
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN ROLE
    |--------------------------------------------------------------------------
    */

    public function assignRoleAt(
        int $userId,
        int $roleId,
        string $assignableType,
        int $assignableId,
    ): UserAssignment {
        // 🔥 Resolve morph safely
        $exists = UserAssignment::where("user_id", $userId)
            ->where("role_id", $roleId)
            ->where("assignable_type", $assignableType)
            ->where("assignable_id", $assignableId)
            ->whereNull("ended_at")
            ->exists();

        if ($exists) {
            abort(409, "user Assignment already exist");
        }

        return DB::transaction(function () use (
            $userId,
            $roleId,
            $assignableType,
            $assignableId,
        ) {
            // 🔥 Expire same role at same scope
            UserAssignment::where([
                "user_id" => $userId,
                "role_id" => $roleId,
                "assignable_type" => $assignableType,
                "assignable_id" => $assignableId,
            ])
                ->whereNull("ended_at")
                ->update(["ended_at" => now()]);

            $assignment = UserAssignment::create([
                "user_id" => $userId,
                "role_id" => $roleId,
                "assignable_type" => $assignableType,
                "assignable_id" => $assignableId,
                "started_at" => now(),
                "assigned_by" => null,
            ]);

            $this->flushUserAssignmentCache(
                $userId,
                $assignableId->organization_id ?? null,
            );

            AuditLogger::log("role_assigned", $assignableId, [
                "role_id" => $roleId,
            ]);

            return $assignment;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | REVOKE
    |--------------------------------------------------------------------------
    */

    public function revokeById(int $assignmentId, User $actor): UserAssignment
    {
        return DB::transaction(function () use ($assignmentId, $actor) {
            $assignment =
                UserAssignment::with("assignable")->find($assignmentId) ??
                throw new RuntimeException("Assignment not found.");

            if ($assignment->ended_at !== null) {
                throw new RuntimeException("Already revoked.");
            }

            $assignment->update(["ended_at" => now()]);

            $this->flushUserAssignmentCache(
                $assignment->user_id,
                $assignment->assignable->organization_id ?? null,
            );

            AuditLogger::log(
                $actor->id,
                "role_revoked",
                $assignment->assignable,
                ["role_id" => $assignment->role_id],
            );

            return $assignment;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE
    |--------------------------------------------------------------------------
    */

    protected function flushUserAssignmentCache(int $userId, ?int $orgId): void
    {
        // Only forget the specific keys related to this user's auth/roles
        Cache::forget("user_permissions_{$userId}");
        if ($orgId) {
            Cache::forget("user_{$userId}_org_{$orgId}_context");
        }
    }
}
