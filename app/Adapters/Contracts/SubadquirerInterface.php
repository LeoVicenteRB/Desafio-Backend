<?php

namespace App\Adapters\Contracts;

use App\DTOs\PixNotificationDTO;
use App\DTOs\SubadqResponse;
use App\DTOs\WithdrawNotificationDTO;

interface SubadquirerInterface
{
    /**
     * Create a PIX payment.
     *
     * @param array<string, mixed> $payload
     */
    public function createPix(array $payload): SubadqResponse;

    /**
     * Create a withdraw request.
     *
     * @param array<string, mixed> $payload
     */
    public function createWithdraw(array $payload): SubadqResponse;

    /**
     * Parse webhook payload to PixNotificationDTO.
     *
     * @param array<string, mixed> $payload
     */
    public function parsePixWebhook(array $payload): ?PixNotificationDTO;

    /**
     * Parse webhook payload to WithdrawNotificationDTO.
     *
     * @param array<string, mixed> $payload
     */
    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO;

    /**
     * Get the subadquirer name.
     */
    public function getName(): string;
}

