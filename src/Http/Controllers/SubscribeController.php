<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use LaravelPlus\Subscribe\DTOs\Subscriber as SubscriberDTO;
use LaravelPlus\Subscribe\Events\ConfirmationEmailSent;
use LaravelPlus\Subscribe\Events\Subscribed;
use LaravelPlus\Subscribe\Events\SubscriptionConfirmed;
use LaravelPlus\Subscribe\Events\Unsubscribed;
use LaravelPlus\Subscribe\Facades\Subscribe;
use LaravelPlus\Subscribe\Http\Requests\SubscribeRequest;
use LaravelPlus\Subscribe\Http\Requests\UnsubscribeRequest;
use LaravelPlus\Subscribe\Mail\ConfirmSubscription;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;

final class SubscribeController extends Controller
{
    public function subscribe(SubscribeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $limited = config('subscribe.rate_limit.enabled', true);
        $clientKey = 'subscribe:client:'.$request->ip();
        $decay = (int) config('subscribe.rate_limit.decay_minutes', 60) * 60;

        if ($limited && RateLimiter::tooManyAttempts($clientKey, (int) config('subscribe.rate_limit.max_attempts', 5))) {
            throw new ThrottleRequestsException();
        }

        if ($limited) {
            RateLimiter::hit($clientKey, $decay);
        }

        // list_id is the list's public uuid — the numeric key never leaves the app.
        $list = ($validated['list_id'] ?? null)
            ? SubscriptionList::where('uuid', $validated['list_id'])->first()
            : SubscriptionList::getDefault();

        $requiresConfirmation = $list?->requiresDoubleOptIn()
            ?? config('subscribe.double_opt_in.enabled', true);

        // createOrFirst survives two concurrent requests for the same address
        // (the unique index decides). An existing row is never overwritten:
        // the request is anonymous, so it cannot prove it owns the address.
        $subscriber = Subscriber::createOrFirst(
            ['email' => $validated['email']],
            [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'source' => $request->header('Referer') ?? 'direct',
                'ip_address' => $request->ip(),
                'status' => $requiresConfirmation ? 'pending' : 'subscribed',
                'confirmation_token' => $requiresConfirmation ? Str::random(64) : null,
                'confirmed_at' => $requiresConfirmation ? null : now(),
            ]
        );

        if ($subscriber->wasRecentlyCreated) {
            $this->subscribeNew($subscriber, $list, $requiresConfirmation);
        } elseif ($requiresConfirmation && $subscriber->status !== 'subscribed') {
            // Pending, or unsubscribed and asking back in: only a mailed link
            // may change the status, so the row stays as it is apart from a
            // token for that link.
            if (!$subscriber->confirmation_token || $subscriber->confirmationTokenExpired()) {
                $subscriber->forceFill(['confirmation_token' => Str::random(64)])->save();
            }

            if ($list) {
                $subscriber->lists()->syncWithoutDetaching([$list->id]);
            }

            $this->sendConfirmation($subscriber);
        }

        // Same body for new, pending and confirmed addresses, so the form
        // cannot be used to find out who is subscribed.
        $message = $requiresConfirmation
            ? __('Please check your email to confirm your subscription.')
            : __('You have been subscribed successfully.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'requires_confirmation' => $requiresConfirmation,
            ]);
        }

        return back()->with('success', $message);
    }

    private function subscribeNew(Subscriber $subscriber, ?SubscriptionList $list, bool $requiresConfirmation): void
    {
        if ($list) {
            $subscriber->lists()->syncWithoutDetaching([$list->id]);
        }

        if (config('subscribe.sync.enabled', true)) {
            $dto = SubscriberDTO::fromArray([
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'status' => $subscriber->status,
            ]);

            Subscribe::subscribe($dto, $list?->provider_id);
        }

        if ($requiresConfirmation) {
            $this->sendConfirmation($subscriber);
        } else {
            event(new Subscribed($subscriber, $list?->id));
        }
    }

    /**
     * Queues the confirmation mail, at most rate_limit.max_confirmations per
     * address per window. Over the limit it is skipped silently so the
     * response does not change.
     */
    private function sendConfirmation(Subscriber $subscriber): void
    {
        if (config('subscribe.rate_limit.enabled', true)) {
            $key = 'subscribe:confirm:'.sha1(mb_strtolower($subscriber->email));

            if (RateLimiter::tooManyAttempts($key, (int) config('subscribe.rate_limit.max_confirmations', 3))) {
                return;
            }

            RateLimiter::hit($key, (int) config('subscribe.rate_limit.decay_minutes', 60) * 60);
        }

        Mail::to($subscriber->email)->queue(new ConfirmSubscription($subscriber));
        event(new ConfirmationEmailSent($subscriber));
    }

    public function confirm(string $token): RedirectResponse
    {
        $subscriber = Subscriber::with('lists')->where('confirmation_token', $token)->first();

        if (!$subscriber || $subscriber->confirmationTokenExpired()) {
            return redirect(config('subscribe.redirects.invalid_token', '/'))
                ->with('error', __('Invalid or expired confirmation link.'));
        }

        $subscriber->confirm();

        event(new SubscriptionConfirmed($subscriber));

        if (config('subscribe.sync.enabled', true)) {
            $dto = SubscriberDTO::fromArray([
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'status' => 'subscribed',
            ]);

            foreach ($subscriber->lists as $list) {
                Subscribe::update($dto, $list->provider_id);
            }
        }

        return redirect(config('subscribe.redirects.confirmed', '/'))
            ->with('success', __('Your subscription has been confirmed.'));
    }

    public function unsubscribeForm(string $token): RedirectResponse
    {
        $subscriber = Subscriber::where('email', base64_decode($token))->first();

        if (!$subscriber || $subscriber->confirmationTokenExpired()) {
            return redirect(config('subscribe.redirects.invalid_token', '/'))
                ->with('error', __('Subscriber not found.'));
        }

        $subscriber->unsubscribe();

        event(new Unsubscribed($subscriber));

        if (config('subscribe.sync.enabled', true)) {
            Subscribe::unsubscribe($subscriber->email);
        }

        return redirect(config('subscribe.redirects.unsubscribed', '/'))
            ->with('success', __('You have been unsubscribed successfully.'));
    }

    public function unsubscribe(UnsubscribeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $subscriber = Subscriber::where('email', $validated['email'])->first();

        if ($validated['list_id'] ?? null) {
            $subscriber->lists()->detach($validated['list_id']);
        } else {
            $subscriber->unsubscribe();
        }

        event(new Unsubscribed($subscriber, $validated['list_id'] ?? null));

        if (config('subscribe.sync.enabled', true)) {
            Subscribe::unsubscribe($subscriber->email, $validated['list_id'] ?? null);
        }

        $message = __('You have been unsubscribed successfully.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
