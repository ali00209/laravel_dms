<?php

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| Enforcement order:
| 1. auth:sanctum        → authentication
| 2. context.guard      → active operational context
| 3. ability:{domain}.* → token scope ceiling
| 4. Controller authz   → fine-grained permission checks
|--------------------------------------------------------------------------
*/

/* =========================================================
 | AUTH CONTROLLERS
 ========================================================= */
use App\Http\Controllers\v1\api\Auth\ContextSwitchController;
use App\Http\Controllers\v1\api\Auth\PermissionController;
use App\Http\Controllers\v1\api\Auth\RoleController;
use App\Http\Controllers\v1\api\Auth\UserAssignmentController;
use App\Http\Controllers\v1\api\Auth\UserContextController;
use App\Http\Controllers\v1\api\Auth\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/* =========================================================
 | API VERSION v1
 ========================================================= */

Route::middleware("auth:sanctum")->get("/test-auth", function () {
    return response()->json([
        "authenticated" => auth()->check(),
        "user" => auth()->user(),
    ]);
});

Route::prefix("v1")->group(function () {
    /* =====================================================
     | PUBLIC AUTH (NO TOKEN)
     ===================================================== */
    require __DIR__ . "/api/auth.php";

    /*
    |--------------------------------------------------------------------------
    | AUTHORITY POLICIES (SYSTEM ONLY)
    |--------------------------------------------------------------------------
    | No permissions
    | is_admin enforced in AuthorizationService
    */
    require __DIR__ . "/api/policies.php";

    /* =====================================================
     | AUTHENTICATED (TOKEN REQUIRED)
     ===================================================== */
    Route::middleware("auth:sanctum")->group(function () {
        Route::get("debug/abilities", function (Request $request) {
            return response()->json([
                "user_id" => $request->user()?->id,
                "is_admin" => $request->user()?->is_admin,
                "token_abilities" => $request->user()?->currentAccessToken()
                    ?->abilities,
            ]);
        });

        /* ================================================
         | USERS (SYSTEM / ORG ADMIN) (NO CONTEXT REQUIRED)
         ================================================== */
        Route::middleware(["ability:user.*"])->group(function () {
            /* Assignment & Context management */
            Route::post("assignments/{assignment}/revoke", [
                UserAssignmentController::class,
                "revokeRoleAt",
            ]);
            Route::get("/active-assignments", [
                UserAssignmentController::class,
                "myActiveAssignments",
            ]);
            Route::apiResource(
                "assignments",
                UserAssignmentController::class,
            )->only(["index", "show"]);
            // View available contexts
            Route::get("contexts", [UserContextController::class, "index"]);
            Route::get("contexts/{context}", [
                UserContextController::class,
                "show",
            ]);
            Route::prefix("users")->group(function () {
                /* Role assignment at scope */
                Route::post("{user_id}/assign-role-at", [
                    UserAssignmentController::class,
                    "assignRoleAt",
                ]);
            });
        });

        /* ===============================================
        | RBAC (NO CONTEXT REQUIRED)
        ================================================= */

        Route::middleware("ability:auth.*")->group(function () {
            Route::prefix("rbac")->group(function () {
                Route::put("roles/{role}/sync-permissions", [
                    RoleController::class,
                    "syncPermissions",
                ]);
                Route::apiResource("roles", RoleController::class);
                Route::apiResource("permissions", PermissionController::class);
            });
            Route::post("users/{user}/restore", [
                UserController::class,
                "restore",
            ]);
            Route::delete("users/{user}/force", [
                UserController::class,
                "forceDelete",
            ]);
            Route::apiResource("users", UserController::class);
        });

        /* =====================================
         | CONTEXT SWITCH (NO CONTEXT REQUIRED)
         ======================================= */
        Route::get("available-contexts", [
            UserContextController::class,
            "available",
        ]);
        Route::post("switch-context", [
            ContextSwitchController::class,
            "switch",
        ]);

        /* =====================================================
         | CONTEXT-BOUND APIs
         ===================================================== */
        Route::middleware("context.guard")->group(function () {
            /* =========================
             | CORE MASTER DATA
             ========================= */
            require __DIR__ . "/api/config.php";

            /* =========================
             | PARTNERS
             ========================= */
            require __DIR__ . "/api/partner.php";

            /* =========================
             | INVENTORY
             ========================= */
            require __DIR__ . "/api/inventory.php";
            /* =========================
             | VOUCHERS
             ========================= */
            require __DIR__ . "/api/voucher.php";
            /* =========================
             | ACCOUNT
             ========================= */
            require __DIR__ . "/api/account.php";
            /* =========================
             | LOGISTICS
             ========================= */
            require __DIR__ . "/api/logistics.php";
        });
    });
});
