<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SubscriptionListModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function testScopePublic(): void
    {
        SubscriptionList::factory()->public()->create();
        SubscriptionList::factory()->private()->create();

        $results = SubscriptionList::query()->public()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is_public);
    }

    #[Test]
    public function testScopeDefault(): void
    {
        SubscriptionList::factory()->default()->create();
        SubscriptionList::factory()->create(['is_default' => false]);

        $results = SubscriptionList::query()->default()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is_default);
    }

    #[Test]
    public function testGetDefault(): void
    {
        $defaultList = SubscriptionList::factory()->default()->create();
        SubscriptionList::factory()->create(['is_default' => false]);

        $result = SubscriptionList::getDefault();

        $this->assertNotNull($result);
        $this->assertTrue($result->is($defaultList));

        // Remove default lists and verify null is returned
        SubscriptionList::query()->update(['is_default' => false]);

        $this->assertNull(SubscriptionList::getDefault());
    }

    #[Test]
    public function testRequiresDoubleOptIn(): void
    {
        $listWithOptIn = SubscriptionList::factory()->withDoubleOptIn()->create();

        $this->assertTrue($listWithOptIn->requiresDoubleOptIn());

        $listWithoutOptIn = SubscriptionList::factory()->create(['double_opt_in' => false]);

        $this->assertFalse($listWithoutOptIn->requiresDoubleOptIn());
    }

    #[Test]
    public function testHasWelcomeEmail(): void
    {
        $listWithWelcome = SubscriptionList::factory()->withWelcomeEmail()->create();

        $this->assertTrue($listWithWelcome->hasWelcomeEmail());

        $listWithoutWelcome = SubscriptionList::factory()->create([
            'welcome_email_enabled' => false,
            'welcome_email_subject' => null,
            'welcome_email_content' => null,
        ]);

        $this->assertFalse($listWithoutWelcome->hasWelcomeEmail());

        // Missing subject should also return false
        $listMissingSubject = SubscriptionList::factory()->create([
            'welcome_email_enabled' => true,
            'welcome_email_subject' => null,
            'welcome_email_content' => 'Some content',
        ]);

        $this->assertFalse($listMissingSubject->hasWelcomeEmail());
    }

    #[Test]
    public function testActiveSubscribersRelationship(): void
    {
        $list = SubscriptionList::factory()->create();

        $subscribedSubscriber = Subscriber::factory()->subscribed()->create();
        $unsubscribedSubscriber = Subscriber::factory()->unsubscribed()->create();

        $list->subscribers()->attach([$subscribedSubscriber->id, $unsubscribedSubscriber->id]);

        $activeSubscribers = $list->activeSubscribers()->get();

        $this->assertCount(1, $activeSubscribers);
        $this->assertTrue($activeSubscribers->first()->is($subscribedSubscriber));
    }
}
