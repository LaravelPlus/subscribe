<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SubscriberModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function testScopeSubscribed(): void
    {
        Subscriber::factory()->subscribed()->create();
        Subscriber::factory()->pending()->create();

        $results = Subscriber::query()->subscribed()->get();

        $this->assertCount(1, $results);
        $this->assertSame('subscribed', $results->first()->status);
    }

    #[Test]
    public function testScopePending(): void
    {
        Subscriber::factory()->subscribed()->create();
        Subscriber::factory()->pending()->create();

        $results = Subscriber::query()->pending()->get();

        $this->assertCount(1, $results);
        $this->assertSame('pending', $results->first()->status);
    }

    #[Test]
    public function testScopeInList(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create();
        $list = SubscriptionList::factory()->create();
        $subscriber->lists()->attach($list);

        $otherSubscriber = Subscriber::factory()->subscribed()->create();

        $results = Subscriber::query()->inList($list->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($subscriber));
    }

    #[Test]
    public function testScopeWithTag(): void
    {
        Subscriber::factory()->subscribed()->create(['tags' => ['newsletter']]);
        Subscriber::factory()->subscribed()->create(['tags' => ['promotions']]);

        $results = Subscriber::query()->withTag('newsletter')->get();

        $this->assertCount(1, $results);
        $this->assertContains('newsletter', $results->first()->tags);
    }

    #[Test]
    public function testConfirmMethod(): void
    {
        $subscriber = Subscriber::factory()->pending()->create();

        $this->assertSame('pending', $subscriber->status);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNotNull($subscriber->confirmation_token);

        $subscriber->confirm();
        $subscriber->refresh();

        $this->assertSame('subscribed', $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->confirmation_token);
    }

    #[Test]
    public function testUnsubscribeMethod(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create();

        $subscriber->unsubscribe();
        $subscriber->refresh();

        $this->assertSame('unsubscribed', $subscriber->status);
    }

    #[Test]
    public function testAddTag(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create(['tags' => []]);

        $subscriber->addTag('new');
        $subscriber->refresh();

        $this->assertTrue($subscriber->hasTag('new'));
    }

    #[Test]
    public function testAddTagDoesNotDuplicate(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create(['tags' => ['existing']]);

        $subscriber->addTag('existing');
        $subscriber->refresh();

        $occurrences = array_count_values($subscriber->tags);

        $this->assertSame(1, $occurrences['existing']);
    }

    #[Test]
    public function testRemoveTag(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create(['tags' => ['a', 'b']]);

        $subscriber->removeTag('a');
        $subscriber->refresh();

        $this->assertFalse($subscriber->hasTag('a'));
        $this->assertTrue($subscriber->hasTag('b'));
    }

    #[Test]
    public function testHasTag(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create(['tags' => ['test']]);

        $this->assertTrue($subscriber->hasTag('test'));
        $this->assertFalse($subscriber->hasTag('other'));
    }

    #[Test]
    public function testIsSubscribed(): void
    {
        $subscribed = Subscriber::factory()->subscribed()->create();
        $pending = Subscriber::factory()->pending()->create();

        $this->assertTrue($subscribed->isSubscribed());
        $this->assertFalse($pending->isSubscribed());
    }

    #[Test]
    public function testGetFullNameAttribute(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertSame('John Doe', $subscriber->full_name);

        $nullNameSubscriber = Subscriber::factory()->subscribed()->create([
            'first_name' => null,
            'last_name' => null,
        ]);

        $this->assertNull($nullNameSubscriber->full_name);
    }

    #[Test]
    public function testListsRelationship(): void
    {
        $subscriber = Subscriber::factory()->subscribed()->create();
        $list = SubscriptionList::factory()->create();

        $subscriber->lists()->attach($list);
        $subscriber->refresh();

        $this->assertCount(1, $subscriber->lists);
        $this->assertTrue($subscriber->lists->first()->is($list));
    }
}
