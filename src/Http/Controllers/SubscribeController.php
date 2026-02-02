<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Nejcc\Subscribe\DTOs\Subscriber as SubscriberDTO;
use Nejcc\Subscribe\Events\ConfirmationEmailSent;
use Nejcc\Subscribe\Events\Subscribed;
use Nejcc\Subscribe\Events\SubscriptionConfirmed;
use Nejcc\Subscribe\Events\Unsubscribed;
use Nejcc\Subscribe\Facades\Subscribe;
use Nejcc\Subscribe\Http\Requests\SubscribeRequest;
use Nejcc\Subscribe\Http\Requests\UnsubscribeRequest;
use Nejcc\Subscribe\Mail\ConfirmSubscription;
use Nejcc\Subscribe\Models\Subscriber;
use Nejcc\Subscribe\Models\SubscriptionList;

final class SubscribeController extends Controller
{
    public function subscribe(SubscribeRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $list = $validated['list_id']
            ? SubscriptionList::find($validated['list_id'])
            : SubscriptionList::getDefault();

        $requiresConfirmation = $list?->requiresDoubleOptIn()
            ?? config('subscribe.double_opt_in.enabled', true);

        $subscriber = Subscriber::updateOrCreate(
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

        if ($requiresConfirmation && $subscriber->confirmation_token) {
            Mail::to($subscriber->email)->send(new ConfirmSubscription($subscriber));
            event(new ConfirmationEmailSent($subscriber));
        } else {
            event(new Subscribed($subscriber, $list?->id));
        }

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

    public function confirm(string $token): RedirectResponse
    {
        $subscriber = Subscriber::where('confirmation_token', $token)->first();

        if (!$subscriber) {
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

        if (!$subscriber) {
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

        if ($validated['list_id']) {
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
