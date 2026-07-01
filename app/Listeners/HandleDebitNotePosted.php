<?php

namespace App\Listeners;

use App\Events\Voucher\DebitNotePosted;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandleDebitNotePosted
{
    public function handle(DebitNotePosted $event): void
    {
        $payload = $event->payload;

        Log::info('DebitNotePosted', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type'   => $payload['reference_type'] ?? null,
            'document_id'     => $payload['reference_id'] ?? null,
            'action'          => 'posted',
            'old_status'      => $payload['old_status'] ?? null,
            'new_status'      => $payload['new_status'] ?? 'posted',
            'user_id'         => $payload['posted_by'] ?? auth()->id(),
            'metadata'        => $payload,
        ]);
    }
}
