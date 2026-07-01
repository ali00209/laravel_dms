<?php

namespace App\Http\Controllers\v1\api\Account;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Journal;
use App\Services\Accounts\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JournalController extends Controller
{
    public function __construct(
        protected JournalEntryService $journalService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $journals = Journal::with('lines.account')
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('financial_year_id'), fn($q) => $q->where('financial_year_id', $request->financial_year_id))
            ->when($request->filled('is_posted'), fn($q) => $q->where('is_posted', $request->boolean('is_posted')))
            ->when($request->filled('date_from'), fn($q) => $q->where('date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->where('date', '<=', $request->date_to))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json($journals);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'memo' => 'nullable|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.memo' => 'nullable|string',
            'auto_post' => 'boolean',
        ]);

        try {
            $journal = $this->journalService->create(
                $validated,
                $validated['lines'],
                $validated['auto_post'] ?? false,
            );

            return response()->json($journal, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $journal = Journal::with('lines.account')->findOrFail($id);
        return response()->json($journal);
    }

    public function post(int $id): JsonResponse
    {
        $journal = Journal::findOrFail($id);

        try {
            $this->journalService->post($journal);
            return response()->json(['message' => 'Journal posted successfully', 'journal' => $journal->fresh()->load('lines.account')]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function reverse(int $id): JsonResponse
    {
        $journal = Journal::findOrFail($id);

        if (! $journal->is_posted) {
            return response()->json(['message' => 'Only posted journals can be reversed'], 422);
        }

        $reversed = $journal->reverse();

        return response()->json(['message' => 'Journal reversed', 'reversal' => $reversed->load('lines.account')]);
    }
}
