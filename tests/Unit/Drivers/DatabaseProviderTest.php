<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Drivers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Subscribe\Drivers\DatabaseProvider;
use LaravelPlus\Subscribe\DTOs\Subscriber as SubscriberDTO;
use LaravelPlus\Subscribe\DTOs\SubscriberList as SubscriberListDTO;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DatabaseProviderTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseProvider $provider;

    private string $testEmail = 'john@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new DatabaseProvider([]);
    }

    #[Test]
    public function test_get_name(): void
    {
        $this->assertSame('database', $this->provider->getName());
    }

    #[Test]
    public function test_subscribe_creates_record(): void
    {
        $subscriber = new SubscriberDTO(
            email: $this->testEmail,
            firstName: 'John',
            lastName: 'Doe',
            status: 'subscribed',
        );

        $result = $this->provider->subscribe($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber added successfully', $result->message);
        $this->assertNotNull($result->providerId);

        $this->assertDatabaseHas('subscribers', [
            'email' => $this->testEmail,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'subscribed',
        ]);
    }

    #[Test]
    public function test_subscribe_with_list_id(): void
    {
        $list = SubscriptionList::factory()->create();

        $subscriber = new SubscriberDTO(
            email: $this->testEmail,
            firstName: 'John',
            status: 'subscribed',
        );

        $result = $this->provider->subscribe($subscriber, (string) $list->id);

        $this->assertTrue($result->success);

        $model = Subscriber::where('email', $this->testEmail)->first();
        $this->assertTrue($model->lists->contains($list));
    }

    #[Test]
    public function test_subscribe_updates_existing(): void
    {
        $subscriber = new SubscriberDTO(
            email: $this->testEmail,
            firstName: 'John',
            status: 'subscribed',
        );

        $this->provider->subscribe($subscriber);
        $this->provider->subscribe($subscriber);

        $this->assertSame(1, Subscriber::where('email', $this->testEmail)->count());
    }

    #[Test]
    public function test_unsubscribe_updates_status(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
        ]);

        $result = $this->provider->unsubscribe($this->testEmail);

        $this->assertTrue($result->success);
        $this->assertSame('Unsubscribed successfully', $result->message);

        $this->assertDatabaseHas('subscribers', [
            'email' => $this->testEmail,
            'status' => 'unsubscribed',
        ]);
    }

    #[Test]
    public function test_unsubscribe_from_list(): void
    {
        $list = SubscriptionList::factory()->create();
        $model = Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
        ]);
        $model->lists()->attach($list->id);

        $result = $this->provider->unsubscribe($this->testEmail, (string) $list->id);

        $this->assertTrue($result->success);

        $model->refresh();
        $this->assertFalse($model->lists->contains($list));
    }

    #[Test]
    public function test_unsubscribe_not_found(): void
    {
        $result = $this->provider->unsubscribe('nonexistent@example.com');

        $this->assertFalse($result->success);
        $this->assertSame('Subscriber not found', $result->message);
    }

    #[Test]
    public function test_update_success(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
            'first_name' => 'John',
        ]);

        $subscriber = new SubscriberDTO(
            email: $this->testEmail,
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $result = $this->provider->update($subscriber);

        $this->assertTrue($result->success);
        $this->assertSame('Subscriber updated successfully', $result->message);

        $this->assertDatabaseHas('subscribers', [
            'email' => $this->testEmail,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    #[Test]
    public function test_is_subscribed_returns_true(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
        ]);

        $this->assertTrue($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_is_subscribed_returns_false(): void
    {
        Subscriber::factory()->unsubscribed()->create([
            'email' => $this->testEmail,
        ]);

        $this->assertFalse($this->provider->isSubscribed($this->testEmail));
    }

    #[Test]
    public function test_get_subscriber_returns_dto(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'company' => 'Acme',
            'tags' => ['vip', 'beta'],
            'source' => 'website',
            'ip_address' => '192.168.1.1',
        ]);

        $subscriber = $this->provider->getSubscriber($this->testEmail);

        $this->assertNotNull($subscriber);
        $this->assertInstanceOf(SubscriberDTO::class, $subscriber);
        $this->assertSame($this->testEmail, $subscriber->email);
        $this->assertSame('John', $subscriber->firstName);
        $this->assertSame('Doe', $subscriber->lastName);
        $this->assertSame('+1234567890', $subscriber->phone);
        $this->assertSame('Acme', $subscriber->company);
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertSame(['vip', 'beta'], $subscriber->tags);
        $this->assertSame('website', $subscriber->source);
        $this->assertSame('192.168.1.1', $subscriber->ipAddress);
    }

    #[Test]
    public function test_get_subscriber_returns_null(): void
    {
        $subscriber = $this->provider->getSubscriber('nonexistent@example.com');

        $this->assertNull($subscriber);
    }

    #[Test]
    public function test_get_lists_returns_array(): void
    {
        SubscriptionList::factory()->count(3)->create();

        $lists = $this->provider->getLists();

        $this->assertCount(3, $lists);
        $this->assertInstanceOf(SubscriberListDTO::class, $lists[0]);
    }

    #[Test]
    public function test_create_list_creates_record(): void
    {
        $listDto = new SubscriberListDTO(
            name: 'My Newsletter',
            description: 'A test list',
            isPublic: true,
            doubleOptIn: false,
        );

        $result = $this->provider->createList($listDto);

        $this->assertTrue($result->success);
        $this->assertSame('List created successfully', $result->message);
        $this->assertNotNull($result->providerId);

        $this->assertDatabaseHas('subscription_lists', [
            'name' => 'My Newsletter',
            'description' => 'A test list',
            'is_public' => true,
            'double_opt_in' => false,
        ]);
    }

    #[Test]
    public function test_add_tags_success(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
            'tags' => [],
        ]);

        $result = $this->provider->addTags($this->testEmail, ['newsletter', 'beta']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags added successfully', $result->message);

        $model = Subscriber::where('email', $this->testEmail)->first();
        $this->assertContains('newsletter', $model->tags);
        $this->assertContains('beta', $model->tags);
    }

    #[Test]
    public function test_remove_tags_success(): void
    {
        Subscriber::factory()->subscribed()->create([
            'email' => $this->testEmail,
            'tags' => ['newsletter', 'beta', 'vip'],
        ]);

        $result = $this->provider->removeTags($this->testEmail, ['newsletter', 'beta']);

        $this->assertTrue($result->success);
        $this->assertSame('Tags removed successfully', $result->message);

        $model = Subscriber::where('email', $this->testEmail)->first();
        $this->assertNotContains('newsletter', $model->tags);
        $this->assertNotContains('beta', $model->tags);
        $this->assertContains('vip', $model->tags);
    }
}
