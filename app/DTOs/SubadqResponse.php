<?php

namespace App\DTOs;

class SubadqResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly ?string $status = null,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function success(string $externalId, ?string $status = null, ?array $data = null): self
    {
        return new self(
            success: true,
            externalId: $externalId,
            status: $status,
            data: $data,
        );
    }

    public static function error(string $error, ?array $data = null): self
    {
        return new self(
            success: false,
            error: $error,
            data: $data,
        );
    }
}

