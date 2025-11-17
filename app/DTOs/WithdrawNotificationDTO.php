<?php

namespace App\DTOs;

class WithdrawNotificationDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $transactionId = null,
        public readonly string $status,
        public readonly float $amount,
        public readonly ?string $completedAt = null,
        public readonly ?array $bankInfo = null,
        public readonly ?array $metadata = null,
    ) {
    }

    /**
     * Map external status to internal status.
     */
    public function getInternalStatus(): string
    {
        return match ($this->status) {
            'SUCCESS', 'DONE' => 'SUCCESS',
            'FAILED', 'ERROR' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
            default => 'PROCESSING',
        };
    }
}

