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
}
