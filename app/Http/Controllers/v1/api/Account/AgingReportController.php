<?php

namespace App\Http\Controllers\v1\api\Account;

use App\Http\Controllers\Controller;
use App\Services\Accounts\AgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgingReportController extends Controller
{
    public function __construct(
        protected AgingService $agingService
    ) {}

    public function accountsReceivable(Request $request): JsonResponse
    {
        $orgId = $request->input('organization_id') ?? auth()->user()?->getCurrentOrganizationId();

        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));

        $result = $this->agingService->accountsReceivable((int) $orgId, $asOfDate);

        return response()->json($result);
    }

    public function accountsPayable(Request $request): JsonResponse
    {
        $orgId = $request->input('organization_id') ?? auth()->user()?->getCurrentOrganizationId();

        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));

        $result = $this->agingService->accountsPayable((int) $orgId, $asOfDate);

        return response()->json($result);
    }
}
