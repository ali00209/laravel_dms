<?php

namespace App\Services\Voucher;

use App\Contracts\ContextScope;
use App\Guards\AuthorityGuard;
use App\Models\User;
use App\Models\Vouchers\BaseVoucher;
use Illuminate\Validation\ValidationException;

class VoucherWorkflowService
{
    public function __construct(
        protected AuthorityGuard $authorityGuard
    ) {}

    protected function resolveHierarchy(User $user): array
    {
        $context = $user->activeContext()
            ?? throw ValidationException::withMessages([
                'context' => 'No active context',
            ]);

        return match (true) {
            $context->outlet_id => ['outlet', $context->outlet_id],
            $context->warehouse_id => ['warehouse', $context->warehouse_id],
            $context->branch_id => ['branch', $context->branch_id],
            default => ['organization', $context->organization_id],
        };
    }

    public function review(User $user, BaseVoucher $voucher, string $voucherType = 'sale_order'): void
    {
        if (! $voucher->isReviewable()) {
            throw ValidationException::withMessages([
                'review' => 'Voucher cannot be reviewed',
            ]);
        }

        [$level, $id] = $this->resolveHierarchy($user);

        $this->authorityGuard->enforce(
            user: $user,
            subject: 'voucher',
            action: 'review',
            target: $voucher instanceof ContextScope ? $voucher : null,
            voucherType: $voucherType,
            roleIds: $user->roles->pluck('id')->toArray(),
        );

        $voucher->markReviewed();
        $voucher->update(['status' => 'reviewed']);
    }

    public function approve(User $user, BaseVoucher $voucher, string $voucherType = 'sale_order'): void
    {
        if (! $voucher->isApprovable()) {
            throw ValidationException::withMessages([
                'approve' => 'Voucher cannot be approved',
            ]);
        }

        [$level, $id] = $this->resolveHierarchy($user);

        $this->authorityGuard->enforce(
            user: $user,
            subject: 'voucher',
            action: 'approve',
            target: $voucher instanceof ContextScope ? $voucher : null,
            voucherType: $voucherType,
            roleIds: $user->roles->pluck('id')->toArray(),
        );

        $voucher->markApproved();
        $voucher->update([
            'status' => 'approved',
            'fulfilled_by_level' => $level,
            'fulfilled_by_id' => $id,
        ]);
    }
}
