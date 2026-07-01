<?php

namespace App\Listeners;

use App\Events\Voucher\VoucherCancelled;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandleVoucherCancelled
{
    public function handle(VoucherCancelled $event): void
    {
        $payload = $event->payload;

        Log::info('VoucherCancelled', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type'   => $payload['reference_type'] ?? null,
            'document_id'     => $payload['reference_id'] ?? null,
            'action'          => 'cancelled',
            'old_status'      => $payload['old_status'] ?? null,
            'new_status'      => $payload['new_status'] ?? 'cancelled',
            'user_id'         => $payload['cancelled_by'] ?? auth()->id(),
            'metadata'        => $payload,
        ]);
    }
}
