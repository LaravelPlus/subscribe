<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use LaravelPlus\Subscribe\Drivers\HubSpotProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class HubSpotProviderTest extends TestCase
{
    private HubSpotProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new HubSpotProvider([
            'api_key' => 'hs-key',
        ]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('hubspot', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'contact-123',
                'properties' => ['email' => $this->testEmail],
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
        $this->assertSame('contact-123', $result->providerId);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/crm/v3/objects/contacts')
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_subscribe_conflict_updates_existing(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['message' => 'Contact already exists', 'status' => 'error'], 409)
                ->push([
                    'total' => 1,
                    'results' => [
                        [
                            'id' => 'contact-456',
                            'properties' => [
                                'email' => $this->testEmail,
                                'firstname' => 'John',
                                'hs_email_optout' => 'false',
                            ],
                        ],
                    ],
                ], 200)
                ->push(['id' => 'contact-456'], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'John',
            lastName: 'Doe',
        );

        $result = $this->provider->subscribe($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber updated successfully', $result->message);
        $this->assertSame('contact-456', $result->providerId);
    }

    #[Test]
    public function test_unsubscribe_success(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'total' => 1,
                    'results' => [
                        [
                            'id' => 'contact-123',
                            'properties' => [
                                'email' => $this->testEmail,
                                'hs_email_optout' => 'false',
                            ],
                        ],
                    ],
                ], 200)
                ->push(['id' => 'contact-123'], 200),
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
                ->push([
                    'total' => 1,
                    'results' => [
                        [
                            'id' => 'contact-123',
                            'properties' => [
                                'email' => $this->testEmail,
                                'hs_email_optout' => 'false',
                            ],
                        ],
                    ],
                ], 200)
                ->push(['id' => 'contact-123'], 200),
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
                'total' => 1,
                'results' => [
                    [
                        'id' => 'contact-123',
                        'properties' => [
                            'email' => $this->testEmail,
                            'hs_email_optout' => 'false',
                        ],
                    ],
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
                'total' => 1,
                'results' => [
                    [
                        'id' => 'contact-123',
                        'properties' => [
                            'email' => $this->testEmail,
                            'firstname' => 'John',
                            'lastname' => 'Doe',
                            'phone' => '+1234567890',
                            'company' => 'Acme',
                            'hs_email_optout' => 'false',
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
        $this->assertSame('contact-123', $subscriber->providerId);
    }

    #[Test]
    public function test_get_lists_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'lists' => [
                    [
                        'listId' => 101,
                        'name' => 'Newsletter',
                        'metaData' => ['size' => 250],
                    ],
                    [
                        'listId' => 102,
                        'name' => 'Product Updates',
                        'metaData' => ['size' => 120],
                    ],
                ],
            ], 200),
        ]);

        $lists = $this->provider->getLists();

        $this->assertCount(2, $lists);
        $this->assertSame('101', $lists[0]->id);
        $this->assertSame('Newsletter', $lists[0]->name);
        $this->assertSame(250, $lists[0]->subscriberCount);
        $this->assertSame('102', $lists[1]->id);
        $this->assertSame('Product Updates', $lists[1]->name);
    }
}
