<?php

namespace App\Listeners;

use App\Events\Voucher\VoucherApproved;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandleVoucherApproved
{
    public function handle(VoucherApproved $event): void
    {
        $payload = $event->payload;

        Log::info('VoucherApproved', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type'   => $payload['reference_type'] ?? null,
            'document_id'     => $payload['reference_id'] ?? null,
            'action'          => 'approved',
            'old_status'      => $payload['old_status'] ?? null,
            'new_status'      => $payload['new_status'] ?? 'approved',
            'user_id'         => $payload['approved_by'] ?? auth()->id(),
            'metadata'        => $payload,
        ]);
    }
}
