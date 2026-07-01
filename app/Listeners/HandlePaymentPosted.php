<?php

namespace App\Listeners;

use App\Events\Voucher\PaymentPosted;
use App\Models\Vouchers\DocumentAuditLog;
use Illuminate\Support\Facades\Log;

class HandlePaymentPosted
{
    public function handle(PaymentPosted $event): void
    {
        $payload = $event->payload;

        Log::info('PaymentPosted', $payload);

        DocumentAuditLog::create([
            'organization_id' => $payload['organization_id'] ?? null,
            'document_type'   => $payload['reference_type'] ?? null,
            'document_id'     => $payload['reference_id'] ?? null,
            'action'          => 'posted',
            'new_status'      => 'posted',
            'user_id'         => $payload['created_by'] ?? auth()->id(),
            'metadata'        => $payload,
        ]);
    }
}
