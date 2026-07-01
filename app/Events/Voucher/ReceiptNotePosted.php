<?php

namespace App\Events\Voucher;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptNotePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function broadcastOn(): array
    {
        $orgId = $this->payload['organization_id'] ?? null;
        return $orgId
            ? [new PrivateChannel("organization.{$orgId}")]
            : [];
    }

    public function broadcastAs(): string
    {
        return 'receipt-note.posted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
