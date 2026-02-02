<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nejcc\Subscribe\Events\ConfirmationEmailSent;
use Nejcc\Subscribe\Events\SubscriberUpdated;
use Nejcc\Subscribe\Events\Unsubscribed;
use Nejcc\Subscribe\Facades\Subscribe;
use Nejcc\Subscribe\Mail\ConfirmSubscription;
use Nejcc\Subscribe\Models\Subscriber;
use Nejcc\Subscribe\Models\SubscriptionList;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Subscriber::query()->with('lists');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('list')) {
            $query->inList($request->input('list'));
        }

        $subscribers = $query->latest()
            ->paginate(25)
            ->withQueryString();

        $lists = SubscriptionList::orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/subscribers/Index', [
            'subscribers' => $subscribers,
            'lists' => $lists,
            'filters' => $request->only(['search', 'status', 'list']),
        ]);
    }

    public function show(Subscriber $subscriber): Response
    {
        $subscriber->load('lists');

        $allLists = SubscriptionList::orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/subscribers/Show', [
            'subscriber' => $subscriber,
            'allLists' => $allLists,
        ]);
    }

    public function update(Request $request, Subscriber $subscriber): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,subscribed,unsubscribed'],
            'tags' => ['nullable', 'array'],
            'lists' => ['nullable', 'array'],
            'lists.*' => ['exists:subscription_lists,id'],
        ]);

        $subscriber->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'status' => $validated['status'],
            'tags' => $validated['tags'] ?? [],
        ]);

        if (isset($validated['lists'])) {
            $subscriber->lists()->sync($validated['lists']);
        }

        event(new SubscriberUpdated($subscriber));

        if (config('subscribe.sync.enabled', true)) {
            $dto = \Nejcc\Subscribe\DTOs\Subscriber::fromArray([
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'phone' => $subscriber->phone,
                'company' => $subscriber->company,
                'tags' => $subscriber->tags ?? [],
                'status' => $subscriber->status,
            ]);

            Subscribe::update($dto);
        }

        return back()->with('success', 'Subscriber updated successfully.');
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        if (config('subscribe.sync.enabled', true)) {
            Subscribe::unsubscribe($subscriber->email);
        }

        event(new Unsubscribed($subscriber));

        $subscriber->delete();

        return redirect()->route('admin.subscribers.subscribers.index')
            ->with('success', 'Subscriber deleted successfully.');
    }

    public function confirm(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->confirm();

        return back()->with('success', 'Subscriber confirmed successfully.');
    }

    public function resendConfirmation(Subscriber $subscriber): RedirectResponse
    {
        if ($subscriber->isConfirmed()) {
            return back()->with('error', 'Subscriber is already confirmed.');
        }

        $subscriber->update([
            'confirmation_token' => Str::random(64),
        ]);

        Mail::to($subscriber->email)->send(new ConfirmSubscription($subscriber));

        event(new ConfirmationEmailSent($subscriber));

        return back()->with('success', 'Confirmation email sent successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Subscriber::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('list')) {
            $query->inList($request->input('list'));
        }

        $fileName = 'subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Email',
                'First Name',
                'Last Name',
                'Phone',
                'Company',
                'Status',
                'Tags',
                'Lists',
                'Source',
                'Subscribed At',
                'Confirmed At',
            ]);

            $query->with('lists')->chunk(1000, function ($subscribers) use ($handle): void {
                foreach ($subscribers as $subscriber) {
                    fputcsv($handle, [
                        $subscriber->email,
                        $subscriber->first_name,
                        $subscriber->last_name,
                        $subscriber->phone,
                        $subscriber->company,
                        $subscriber->status,
                        implode(', ', $subscriber->tags ?? []),
                        $subscriber->lists->pluck('name')->implode(', '),
                        $subscriber->source,
                        $subscriber->created_at?->toDateTimeString(),
                        $subscriber->confirmed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
