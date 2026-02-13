<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\DTOs;

final readonly class SyncResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public ?string $providerId = null,
        public ?string $errorCode = null,
        public array $data = [],
    ) {}

    public static function success(?string $message = null, ?string $providerId = null, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            providerId: $providerId,
            data: $data,
        );
    }

    public static function failure(string $message, ?string $errorCode = null, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            errorCode: $errorCode,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'provider_id' => $this->providerId,
            'error_code' => $this->errorCode,
            'data' => $this->data,
        ];
    }
}
