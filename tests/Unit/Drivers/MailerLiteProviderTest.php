<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use LaravelPlus\Subscribe\Drivers\MailerLiteProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MailerLiteProviderTest extends TestCase
{
    private MailerLiteProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new MailerLiteProvider([
            'api_key' => 'test-key',
            'default_group_id' => 'group-1',
        ]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('mailerlite', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'sub-123',
                    'email' => $this->testEmail,
                ],
            ], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'John',
            lastName: 'Doe',
            status: 'subscribed',
        );

        $result = $this->provider->subscribe($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber added successfully', $result->message);
        $this->assertSame('sub-123', $result->providerId);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/subscribers')
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_unsubscribe_with_list_id(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['data' => ['id' => 'sub-123', 'email' => $this->testEmail]], 200)
                ->push(null, 204),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail, 'group-1');

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);
    }

    #[Test]
    public function test_unsubscribe_without_list_id(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['data' => ['id' => 'sub-123', 'email' => $this->testEmail]], 200)
                ->push(['data' => ['id' => 'sub-123', 'status' => 'unsubscribed']], 200),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail);

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);
    }

    #[Test]
    public function test_update_success(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['data' => ['id' => 'sub-123', 'email' => $this->testEmail]], 200)
                ->push(['data' => ['id' => 'sub-123']], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $result = $this->provider->update($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber updated successfully', $result->message);
    }

    #[Test]
    public function test_is_subscribed_returns_true(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'sub-123',
                    'email' => $this->testEmail,
                    'status' => 'active',
                    'groups' => [],
                ],
            ], 200),
        ]);

        $this->assertTrue($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_get_subscriber_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'id' => 'sub-123',
                    'email' => $this->testEmail,
                    'status' => 'active',
                    'fields' => [
                        'name' => 'John',
                        'last_name' => 'Doe',
                        'phone' => '+1234567890',
                        'company' => 'Acme',
                    ],
                    'groups' => [
                        ['id' => 'group-1'],
                        ['id' => 'group-2'],
                    ],
                    'ip_address' => '192.168.1.1',
                ],
            ], 200),
        ]);

        $subscriber = $this->provider->getSubscriber($this->testEmail);

        $this->assertNotNull($subscriber);
        $this->assertSame($this->testEmail, $subscriber->email);
        $this->assertSame('John', $subscriber->firstName);
        $this->assertSame('Doe', $subscriber->lastName);
        $this->assertSame('+1234567890', $subscriber->phone);
        $this->assertSame('Acme', $subscriber->company);
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertSame('sub-123', $subscriber->providerId);
        $this->assertSame(['group-1', 'group-2'], $subscriber->lists);
        $this->assertSame('192.168.1.1', $subscriber->ipAddress);
    }

    #[Test]
    public function test_get_lists_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 'grp-1',
                        'name' => 'Newsletter',
                        'active_count' => 100,
                    ],
                    [
                        'id' => 'grp-2',
                        'name' => 'Updates',
                        'active_count' => 50,
                    ],
                ],
            ], 200),
        ]);

        $lists = $this->provider->getLists();

        $this->assertCount(2, $lists);
        $this->assertSame('grp-1', $lists[0]->id);
        $this->assertSame('Newsletter', $lists[0]->name);
        $this->assertSame(100, $lists[0]->subscriberCount);
        $this->assertSame('grp-2', $lists[1]->id);
        $this->assertSame('Updates', $lists[1]->name);
    }
}
