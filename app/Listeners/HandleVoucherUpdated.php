<?php

namespace App\Listeners;

use App\Events\Voucher\VoucherUpdated;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandleVoucherUpdated
{
    public function handle(VoucherUpdated $event): void
    {
        $payload = $event->payload;

        Log::info('VoucherUpdated', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type'   => $payload['reference_type'] ?? null,
            'document_id'     => $payload['reference_id'] ?? null,
            'action'          => 'updated',
            'old_status'      => $payload['old_status'] ?? null,
            'new_status'      => $payload['new_status'] ?? null,
            'user_id'         => $payload['updated_by'] ?? auth()->id(),
            'metadata'        => $payload,
        ]);
    }
}
