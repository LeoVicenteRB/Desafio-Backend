<?php

namespace App\DTOs;

class PixNotificationDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly float $amount,
        public readonly ?string $payerName = null,
        public readonly ?string $payerDocument = null,
        public readonly ?string $paymentDate = null,
        public readonly ?array $metadata = null,
    ) {
    }

    /**
     * Map external status to internal status.
     */
    public function getInternalStatus(): string
    {
        return match ($this->status) {
            'CONFIRMED', 'PAID' => 'CONFIRMED',
            'FAILED', 'ERROR' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
            default => 'PROCESSING',
        };
    }
}

