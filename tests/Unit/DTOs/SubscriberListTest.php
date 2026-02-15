<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\DTOs;

use LaravelPlus\Subscribe\DTOs\SubscriberList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubscriberListTest extends TestCase
{
    #[Test]
    public function testFromArrayCreatesSubscriberList(): void
    {
        $data = [
            'id' => 'list-1',
            'name' => 'Newsletter',
            'description' => 'Weekly newsletter',
            'provider_id' => 'prov-list-1',
            'subscriber_count' => 1500,
            'is_public' => false,
            'double_opt_in' => false,
        ];

        $list = SubscriberList::fromArray($data);

        $this->assertSame('list-1', $list->id);
        $this->assertSame('Newsletter', $list->name);
        $this->assertSame('Weekly newsletter', $list->description);
        $this->assertSame('prov-list-1', $list->providerId);
        $this->assertSame(1500, $list->subscriberCount);
        $this->assertFalse($list->isPublic);
        $this->assertFalse($list->doubleOptIn);
    }

    #[Test]
    public function testToArrayReturnsCorrectStructure(): void
    {
        $list = new SubscriberList(
            name: 'Updates',
            id: 'list-2',
            description: 'Product updates',
            providerId: 'prov-list-2',
            subscriberCount: 200,
            isPublic: true,
            doubleOptIn: false,
        );

        $array = $list->toArray();

        $this->assertSame('list-2', $array['id']);
        $this->assertSame('Updates', $array['name']);
        $this->assertSame('Product updates', $array['description']);
        $this->assertSame('prov-list-2', $array['provider_id']);
        $this->assertSame(200, $array['subscriber_count']);
        $this->assertTrue($array['is_public']);
        $this->assertFalse($array['double_opt_in']);
    }

    #[Test]
    public function testDefaultValues(): void
    {
        $list = new SubscriberList(name: 'Default List');

        $this->assertTrue($list->isPublic);
        $this->assertTrue($list->doubleOptIn);
        $this->assertNull($list->id);
        $this->assertNull($list->description);
        $this->assertNull($list->providerId);
        $this->assertNull($list->subscriberCount);
    }

    #[Test]
    public function testFromArrayWithMinimalData(): void
    {
        $list = SubscriberList::fromArray(['name' => 'Minimal List']);

        $this->assertSame('Minimal List', $list->name);
        $this->assertNull($list->id);
        $this->assertNull($list->description);
        $this->assertNull($list->providerId);
        $this->assertNull($list->subscriberCount);
        $this->assertTrue($list->isPublic);
        $this->assertTrue($list->doubleOptIn);
    }
}
