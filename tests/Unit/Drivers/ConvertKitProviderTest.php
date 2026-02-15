<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use LaravelPlus\Subscribe\Drivers\ConvertKitProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConvertKitProviderTest extends TestCase
{
    private ConvertKitProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ConvertKitProvider([
            'api_key' => 'ck-key',
            'api_secret' => 'ck-secret',
            'default_form_id' => 'form-1',
        ]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('convertkit', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'subscription' => [
                    'subscriber' => [
                        'id' => 12345,
                    ],
                ],
            ], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'John',
            status: 'subscribed',
        );

        $result = $this->provider->subscribe($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber added successfully', $result->message);
        $this->assertSame('12345', $result->providerId);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/forms/form-1/subscribe')
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_subscribe_without_form_id(): void
    {
        $provider = new ConvertKitProvider([
            'api_key' => 'ck-key',
            'api_secret' => 'ck-secret',
        ]);

        $subscriber = new Subscriber(email: $this->testEmail);

        $result = $provider->subscribe($subscriber);

        $this->assertFalse($result->success);
        $this->assertSame('Form ID is required for ConvertKit', $result->message);
    }

    #[Test]
    public function test_unsubscribe_success(): void
    {
        Http::fake([
            '*' => Http::response(['subscriber' => ['id' => 12345]], 200),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail);

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/unsubscribe')
                && $request->method() === 'PUT');
    }

    #[Test]
    public function test_update_success(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'subscribers' => [
                        ['id' => 12345, 'email_address' => $this->testEmail, 'state' => 'active', 'fields' => []],
                    ],
                ], 200)
                ->push(['subscriber' => ['id' => 12345]], 200),
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
    public function test_update_not_found(): void
    {
        Http::fake([
            '*' => Http::response([
                'subscribers' => [],
            ], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'Jane',
        );

        $result = $this->provider->update($subscriber);

        $this->assertFalse($result->success);
        $this->assertSame('Subscriber not found', $result->message);
    }

    #[Test]
    public function test_is_subscribed(): void
    {
        Http::fake([
            '*' => Http::response([
                'subscribers' => [
                    ['id' => 12345, 'email_address' => $this->testEmail, 'state' => 'active', 'fields' => []],
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
                'subscribers' => [
                    [
                        'id' => 12345,
                        'email_address' => $this->testEmail,
                        'first_name' => 'John',
                        'state' => 'active',
                        'fields' => [
                            'last_name' => 'Doe',
                            'phone' => '+1234567890',
                            'company' => 'Acme',
                        ],
                    ],
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
        $this->assertSame('12345', $subscriber->providerId);
    }

    #[Test]
    public function test_add_tags_success(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['tags' => [['id' => 99, 'name' => 'newsletter']]], 200)
                ->push(['subscription' => ['subscriber' => ['id' => 12345]]], 200),
        ]);

        $result = $this->provider->addTags($this->testEmail, ['newsletter']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags added successfully', $result->message);
    }

    #[Test]
    public function test_remove_tags_success(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'subscribers' => [
                        ['id' => 12345, 'email_address' => $this->testEmail, 'state' => 'active', 'fields' => []],
                    ],
                ], 200)
                ->push(['tags' => [['id' => 99, 'name' => 'newsletter']]], 200)
                ->push(null, 204),
        ]);

        $result = $this->provider->removeTags($this->testEmail, ['newsletter']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags removed successfully', $result->message);
    }
}
