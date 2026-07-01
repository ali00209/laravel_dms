<?php

namespace App\Listeners;

use App\Events\Voucher\VoucherPosted;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandleVoucherPosted
{
    public function handle(VoucherPosted $event): void
    {
        $payload = $event->payload;

        Log::info('VoucherPosted', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type' => $payload['document_type'] ?? 'unknown',
            'document_id' => $payload['reference_id'] ?? null,
            'event_type' => 'voucher.posted',
            'old_values' => null,
            'new_values' => $payload,
            'created_by' => $payload['created_by'] ?? null,
        ]);
    }
}
