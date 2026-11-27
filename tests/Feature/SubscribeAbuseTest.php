<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LaravelPlus\Subscribe\Mail\ConfirmSubscription;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SubscribeAbuseTest extends TestCase
{
    use RefreshDatabase;

    private int $ip = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /** Each call comes from a fresh client IP unless $ip is given. */
    private function subscribeFrom(array $data, ?string $ip = null): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip ?? '10.0.0.'.$this->ip++])
            ->postJson('/subscribe', $data);
    }

    #[Test]
    public function new_address_is_created_pending_and_mail_is_queued(): void
    {
        $this->subscribeFrom(['email' => 'new@example.com'])->assertOk();

        $this->assertSame('pending', Subscriber::where('email', 'new@example.com')->value('status'));
        Mail::assertQueued(ConfirmSubscription::class, 1);
        Mail::assertNothingSent();
    }

    #[Test]
    public function confirmed_subscriber_is_left_untouched(): void
    {
        $list = SubscriptionList::factory()->create();
        $subscriber = Subscriber::factory()->subscribed()->create([
            'email' => 'confirmed@example.com',
            'first_name' => 'Original',
            'ip_address' => '1.1.1.1',
            'source' => 'signup',
        ]);
        $before = $subscriber->fresh()->getAttributes();

        $this->subscribeFrom([
            'email' => 'confirmed@example.com',
            'first_name' => 'Attacker',
            'list_id' => $list->uuid,
        ])->assertOk();

        $this->assertSame($before, $subscriber->fresh()->getAttributes());
        $this->assertSame(0, $subscriber->lists()->count());
        Mail::assertNothingOutgoing();
    }

    #[Test]
    public function pending_subscriber_gets_at_most_the_configured_resends(): void
    {
        $subscriber = Subscriber::factory()->pending()->create(['email' => 'pending@example.com']);
        $before = $subscriber->fresh()->getAttributes();

        for ($i = 0; $i < 10; $i++) {
            $this->subscribeFrom(['email' => 'pending@example.com', 'first_name' => 'Attacker'])->assertOk();
        }

        Mail::assertQueued(ConfirmSubscription::class, 3);
        $this->assertSame($before, $subscriber->fresh()->getAttributes());
    }

    #[Test]
    public function response_is_identical_for_new_pending_and_confirmed_addresses(): void
    {
        Subscriber::factory()->pending()->create(['email' => 'pending@example.com']);
        Subscriber::factory()->subscribed()->create(['email' => 'confirmed@example.com']);

        $bodies = array_map(
            fn (string $email) => $this->subscribeFrom(['email' => $email])->assertOk()->getContent(),
            ['new@example.com', 'pending@example.com', 'confirmed@example.com'],
        );

        $this->assertCount(1, array_unique($bodies));
    }

    #[Test]
    public function client_is_throttled_after_max_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->subscribeFrom(['email' => "user{$i}@example.com"], '10.9.9.9')->assertOk();
        }

        $this->subscribeFrom(['email' => 'user6@example.com'], '10.9.9.9')->assertStatus(429);
        $this->assertSame(5, Subscriber::count());
    }

    #[Test]
    public function unsubscribed_address_needs_the_mailed_link_to_come_back(): void
    {
        $subscriber = Subscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

        $this->subscribeFrom(['email' => 'gone@example.com'])->assertOk();

        $subscriber->refresh();
        $this->assertSame('unsubscribed', $subscriber->status);
        Mail::assertQueued(ConfirmSubscription::class, 1);

        $this->get('/subscribe/confirm/'.$subscriber->confirmation_token)->assertRedirect();
        $this->assertSame('subscribed', $subscriber->fresh()->status);
    }

    #[Test]
    public function confirmation_link_expires_after_the_configured_hours(): void
    {
        $subscriber = Subscriber::factory()->pending()->create(['email' => 'late@example.com']);
        $token = $subscriber->confirmation_token;

        $this->travel(49)->hours();

        $this->get('/subscribe/confirm/'.$token)->assertRedirect();
        $this->assertSame('pending', $subscriber->fresh()->status);
    }

    #[Test]
    public function resubscribing_with_an_expired_token_mails_a_fresh_one(): void
    {
        $subscriber = Subscriber::factory()->pending()->create(['email' => 'late@example.com']);
        $oldToken = $subscriber->confirmation_token;

        $this->travel(49)->hours();
        $this->subscribeFrom(['email' => 'late@example.com'])->assertOk();

        $newToken = $subscriber->fresh()->confirmation_token;
        $this->assertNotSame($oldToken, $newToken);

        $this->get('/subscribe/confirm/'.$newToken)->assertRedirect();
        $this->assertSame('subscribed', $subscriber->fresh()->status);
    }

    #[Test]
    public function referer_is_capped_to_the_source_column(): void
    {
        $this->withHeader('Referer', 'https://example.com/'.str_repeat('a', 500))
            ->subscribeFrom(['email' => 'ref@example.com'])
            ->assertOk();

        $this->assertSame(255, mb_strlen(Subscriber::where('email', 'ref@example.com')->value('source')));
    }

    #[Test]
    public function database_sync_keeps_source_and_ip_of_a_new_signup(): void
    {
        $this->withHeader('Referer', 'https://example.com/landing')
            ->subscribeFrom(['email' => 'kept@example.com'], '10.7.7.7')
            ->assertOk();

        $subscriber = Subscriber::where('email', 'kept@example.com')->first();
        $this->assertSame('https://example.com/landing', $subscriber->source);
        $this->assertSame('10.7.7.7', $subscriber->ip_address);
    }

    #[Test]
    public function database_sync_on_confirm_keeps_the_stored_profile(): void
    {
        $subscriber = Subscriber::factory()->pending()->create([
            'phone' => '+386 1 234',
            'company' => 'Acme',
            'tags' => ['vip'],
            'attributes' => ['plan' => 'pro'],
            'source' => 'landing',
            'ip_address' => '10.1.1.1',
        ]);
        $subscriber->lists()->attach(SubscriptionList::factory()->create()->id);

        $this->get('/subscribe/confirm/'.$subscriber->confirmation_token)->assertRedirect();

        $subscriber->refresh();
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertSame('+386 1 234', $subscriber->phone);
        $this->assertSame('Acme', $subscriber->company);
        $this->assertSame(['vip'], $subscriber->tags);
        $this->assertSame(['plan' => 'pro'], $subscriber->attributes);
        $this->assertSame('landing', $subscriber->source);
    }
}
