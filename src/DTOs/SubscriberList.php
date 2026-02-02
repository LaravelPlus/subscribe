<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\DTOs;

final readonly class SubscriberList
{
    public function __construct(
        public string $name,
        public ?string $id = null,
        public ?string $description = null,
        public ?string $providerId = null,
        public ?int $subscriberCount = null,
        public bool $isPublic = true,
        public bool $doubleOptIn = true,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'provider_id' => $this->providerId,
            'subscriber_count' => $this->subscriberCount,
            'is_public' => $this->isPublic,
            'double_opt_in' => $this->doubleOptIn,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            id: $data['id'] ?? null,
            description: $data['description'] ?? null,
            providerId: $data['provider_id'] ?? null,
            subscriberCount: $data['subscriber_count'] ?? null,
            isPublic: $data['is_public'] ?? true,
            doubleOptIn: $data['double_opt_in'] ?? true,
        );
    }
}
