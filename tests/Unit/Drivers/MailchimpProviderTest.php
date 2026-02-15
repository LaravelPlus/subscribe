<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Support\Facades\Http;
use LaravelPlus\Subscribe\Drivers\MailchimpProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MailchimpProviderTest extends TestCase
{
    private MailchimpProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new MailchimpProvider([
            'api_key' => 'test-key',
            'server_prefix' => 'us1',
            'default_list_id' => 'list-123',
        ]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('mailchimp', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_success(): void
    {
        $hash = md5(mb_strtolower($this->testEmail));

        Http::fake([
            '*' => Http::response(['id' => 'member-id-123'], 200),
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
        $this->assertSame('member-id-123', $result->providerId);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/lists/list-123/members/{$hash}")
                && $request->method() === 'PUT');
    }

    #[Test]
    public function test_subscribe_without_list_id(): void
    {
        $provider = new MailchimpProvider([
            'api_key' => 'test-key',
            'server_prefix' => 'us1',
        ]);

        $subscriber = new Subscriber(email: $this->testEmail);

        $result = $provider->subscribe($subscriber);

        $this->assertFalse($result->success);
        $this->assertSame('List ID is required for Mailchimp', $result->message);
    }

    #[Test]
    public function test_unsubscribe_success(): void
    {
        $hash = md5(mb_strtolower($this->testEmail));

        Http::fake([
            '*' => Http::response(['status' => 'unsubscribed'], 200),
        ]);

        $result = $this->provider->unsubscribe($this->testEmail);

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/lists/list-123/members/{$hash}")
                && $request->method() === 'PATCH');
    }

    #[Test]
    public function test_unsubscribe_without_list_id(): void
    {
        $provider = new MailchimpProvider([
            'api_key' => 'test-key',
            'server_prefix' => 'us1',
        ]);

        $result = $provider->unsubscribe($this->testEmail);

        $this->assertFalse($result->success);
        $this->assertSame('List ID is required for Mailchimp', $result->message);
    }

    #[Test]
    public function test_update_success(): void
    {
        $hash = md5(mb_strtolower($this->testEmail));

        Http::fake([
            '*' => Http::response(['id' => 'member-id-123'], 200),
        ]);

        $subscriber = new Subscriber(
            email: $this->testEmail,
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $result = $this->provider->update($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber updated successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/lists/list-123/members/{$hash}")
                && $request->method() === 'PATCH');
    }

    #[Test]
    public function test_is_subscribed_returns_true(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'subscribed'], 200),
        ]);

        $this->assertTrue($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_is_subscribed_returns_false(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'unsubscribed'], 200),
        ]);

        $this->assertFalse($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_get_subscriber_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'member-abc',
                'email_address' => $this->testEmail,
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => 'John',
                    'LNAME' => 'Doe',
                    'PHONE' => '+1234567890',
                    'COMPANY' => 'Acme',
                ],
                'tags' => [
                    ['name' => 'vip'],
                    ['name' => 'beta'],
                ],
                'ip_signup' => '192.168.1.1',
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
        $this->assertSame('member-abc', $subscriber->providerId);
        $this->assertSame(['vip', 'beta'], $subscriber->tags);
        $this->assertSame('192.168.1.1', $subscriber->ipAddress);
        $this->assertSame(['list-123'], $subscriber->lists);
    }

    #[Test]
    public function test_get_subscriber_returns_null(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 404, 'detail' => 'Not found'], 404),
        ]);

        $subscriber = $this->provider->getSubscriber($this->testEmail);

        $this->assertNull($subscriber);
    }

    #[Test]
    public function test_add_tags_success(): void
    {
        $hash = md5(mb_strtolower($this->testEmail));

        Http::fake([
            '*' => Http::response(null, 204),
        ]);

        $result = $this->provider->addTags($this->testEmail, ['newsletter', 'beta']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags added successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/lists/list-123/members/{$hash}/tags")
                && $request->method() === 'POST');
    }

    #[Test]
    public function test_remove_tags_success(): void
    {
        $hash = md5(mb_strtolower($this->testEmail));

        Http::fake([
            '*' => Http::response(null, 204),
        ]);

        $result = $this->provider->removeTags($this->testEmail, ['newsletter']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags removed successfully', $result->message);

        Http::assertSent(fn ($request) => str_contains($request->url(), "/lists/list-123/members/{$hash}/tags")
                && $request->method() === 'POST');
    }
}
