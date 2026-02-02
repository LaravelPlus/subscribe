<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\DTOs;

final readonly class Subscriber
{
    /**
     * @param  array<string, mixed>  $attributes  Custom attributes/fields
     * @param  array<string>  $tags  Tags/segments
     * @param  array<string>  $lists  List IDs the subscriber belongs to
     */
    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $company = null,
        public array $attributes = [],
        public array $tags = [],
        public array $lists = [],
        public ?string $source = null,
        public ?string $ipAddress = null,
        public ?string $status = 'pending',
        public ?string $providerId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'company' => $this->company,
            'attributes' => $this->attributes,
            'tags' => $this->tags,
            'lists' => $this->lists,
            'source' => $this->source,
            'ip_address' => $this->ipAddress,
            'status' => $this->status,
            'provider_id' => $this->providerId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            phone: $data['phone'] ?? null,
            company: $data['company'] ?? null,
            attributes: $data['attributes'] ?? [],
            tags: $data['tags'] ?? [],
            lists: $data['lists'] ?? [],
            source: $data['source'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            status: $data['status'] ?? 'pending',
            providerId: $data['provider_id'] ?? null,
        );
    }

    public function getFullName(): ?string
    {
        $parts = array_filter([$this->firstName, $this->lastName]);

        return $parts ? implode(' ', $parts) : null;
    }
}
