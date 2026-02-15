<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use LaravelPlus\Subscribe\Drivers\BrevoProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class BrevoProviderTest extends TestCase
{
    private BrevoProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new BrevoProvider([
            'api_key' => 'test-key',
            'default_list_id' => '5',
        ]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('brevo', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_success(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 42], 200),
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
        $this->assertSame($this->testEmail, $result->providerId);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/contacts')
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_unsubscribe_with_list_id(): void
    {
        Http::fake([
            '*' => Http::response(null, 204),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail, '5');

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/contacts/lists/5/contacts/remove')
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_unsubscribe_without_list_id(): void
    {
        Http::fake([
            '*' => Http::response(null, 204),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail);

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/contacts/{$this->testEmail}")
                && $request->method() === 'PUT');
    }

    #[Test]
    public function test_update_success(): void
    {
        Http::fake([
            '*' => Http::response(null, 204),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $result = $this->provider->update($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber updated successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/contacts/{$this->testEmail}")
                && $request->method() === 'PUT');
    }

    #[Test]
    public function test_is_subscribed_returns_true(): void
    {
        Http::fake([
            '*' => Http::response([
                'email' => $this->testEmail,
                'emailBlacklisted' => false,
                'listIds' => [5, 10],
            ], 200),
        ]);

        $this->assertTrue($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_get_subscriber_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 42,
                'email' => $this->testEmail,
                'emailBlacklisted' => false,
                'attributes' => [
                    'FIRSTNAME' => 'John',
                    'LASTNAME' => 'Doe',
                    'SMS' => '+1234567890',
                    'COMPANY' => 'Acme',
                ],
                'listIds' => [5, 10],
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
        $this->assertSame('42', $subscriber->providerId);
        $this->assertSame(['5', '10'], $subscriber->lists);
    }

    #[Test]
    public function test_get_lists_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'lists' => [
                    [
                        'id' => 1,
                        'name' => 'Newsletter',
                        'totalSubscribers' => 150,
                    ],
                    [
                        'id' => 2,
                        'name' => 'Product Updates',
                        'totalSubscribers' => 75,
                    ],
                ],
            ], 200),
        ]);

        $lists = $this->provider->getLists();

        $this->assertCount(2, $lists);
        $this->assertSame('1', $lists[0]->id);
        $this->assertSame('Newsletter', $lists[0]->name);
        $this->assertSame(150, $lists[0]->subscriberCount);
        $this->assertSame('2', $lists[1]->id);
        $this->assertSame('Product Updates', $lists[1]->name);
    }
}
