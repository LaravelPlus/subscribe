<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\DTOs;

use LaravelPlus\Subscribe\DTOs\Subscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubscriberTest extends TestCase
{
    #[Test]
    public function testFromArrayCreatesSubscriber(): void
    {
        $data = [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'company' => 'Acme Inc',
            'attributes' => ['role' => 'developer'],
            'tags' => ['newsletter', 'beta'],
            'lists' => ['list-1', 'list-2'],
            'source' => 'website',
            'ip_address' => '192.168.1.1',
            'status' => 'subscribed',
            'provider_id' => 'provider-123',
        ];

        $subscriber = Subscriber::fromArray($data);

        $this->assertSame('john@example.com', $subscriber->email);
        $this->assertSame('John', $subscriber->firstName);
        $this->assertSame('Doe', $subscriber->lastName);
        $this->assertSame('+1234567890', $subscriber->phone);
        $this->assertSame('Acme Inc', $subscriber->company);
        $this->assertSame(['role' => 'developer'], $subscriber->attributes);
        $this->assertSame(['newsletter', 'beta'], $subscriber->tags);
        $this->assertSame(['list-1', 'list-2'], $subscriber->lists);
        $this->assertSame('website', $subscriber->source);
        $this->assertSame('192.168.1.1', $subscriber->ipAddress);
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertSame('provider-123', $subscriber->providerId);
    }

    #[Test]
    public function testToArrayReturnsCorrectStructure(): void
    {
        $subscriber = new Subscriber(
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            phone: '+0987654321',
            company: 'Corp LLC',
            attributes: ['tier' => 'premium'],
            tags: ['vip'],
            lists: ['list-a'],
            source: 'api',
            ipAddress: '10.0.0.1',
            status: 'subscribed',
            providerId: 'prov-456',
        );

        $array = $subscriber->toArray();

        $this->assertSame('jane@example.com', $array['email']);
        $this->assertSame('Jane', $array['first_name']);
        $this->assertSame('Smith', $array['last_name']);
        $this->assertSame('+0987654321', $array['phone']);
        $this->assertSame('Corp LLC', $array['company']);
        $this->assertSame(['tier' => 'premium'], $array['attributes']);
        $this->assertSame(['vip'], $array['tags']);
        $this->assertSame(['list-a'], $array['lists']);
        $this->assertSame('api', $array['source']);
        $this->assertSame('10.0.0.1', $array['ip_address']);
        $this->assertSame('subscribed', $array['status']);
        $this->assertSame('prov-456', $array['provider_id']);
    }

    #[Test]
    public function testGetFullNameWithBothNames(): void
    {
        $subscriber = new Subscriber(
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
        );

        $this->assertSame('John Doe', $subscriber->getFullName());
    }

    #[Test]
    public function testGetFullNameWithOnlyFirstName(): void
    {
        $subscriber = new Subscriber(
            email: 'john@example.com',
            firstName: 'John',
        );

        $this->assertSame('John', $subscriber->getFullName());
    }

    #[Test]
    public function testGetFullNameReturnsNullWhenEmpty(): void
    {
        $subscriber = new Subscriber(email: 'test@example.com');

        $this->assertNull($subscriber->getFullName());
    }

    #[Test]
    public function testDefaultStatusIsPending(): void
    {
        $subscriber = new Subscriber(email: 'test@example.com');

        $this->assertSame('pending', $subscriber->status);
    }

    #[Test]
    public function testFromArrayWithMinimalData(): void
    {
        $subscriber = Subscriber::fromArray(['email' => 'minimal@example.com']);

        $this->assertSame('minimal@example.com', $subscriber->email);
        $this->assertNull($subscriber->firstName);
        $this->assertNull($subscriber->lastName);
        $this->assertNull($subscriber->phone);
        $this->assertNull($subscriber->company);
        $this->assertSame([], $subscriber->attributes);
        $this->assertSame([], $subscriber->tags);
        $this->assertSame([], $subscriber->lists);
        $this->assertNull($subscriber->source);
        $this->assertNull($subscriber->ipAddress);
        $this->assertSame('pending', $subscriber->status);
        $this->assertNull($subscriber->providerId);
    }
}
