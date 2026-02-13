<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LaravelPlus\Subscribe\DTOs\SubscriberList as SubscriberListDTO;
use LaravelPlus\Subscribe\Facades\Subscribe;
use LaravelPlus\Subscribe\Http\Requests\StoreListRequest;
use LaravelPlus\Subscribe\Http\Requests\UpdateListRequest;
use LaravelPlus\Subscribe\Models\SubscriptionList;

final class ListController extends Controller
{
    public function index(): Response
    {
        $lists = SubscriptionList::withCount(['subscribers', 'activeSubscribers'])
            ->orderBy('name')
            ->paginate(25);

        return Inertia::render('admin/subscribers/lists/Index', [
            'lists' => $lists,
        ]);
    }

    public function store(StoreListRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['is_default'] ?? false) {
            SubscriptionList::where('is_default', true)->update(['is_default' => false]);
        }

        $list = SubscriptionList::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        if (config('subscribe.sync.enabled', true)) {
            $dto = SubscriberListDTO::fromArray([
                'name' => $list->name,
                'description' => $list->description,
                'is_public' => $list->is_public,
                'double_opt_in' => $list->double_opt_in,
            ]);

            $result = Subscribe::createList($dto);

            if ($result->success && $result->providerId) {
                $list->update(['provider_id' => $result->providerId]);
            }
        }

        return redirect()->route('admin.subscribers.lists.index')
            ->with('success', 'List created successfully.');
    }

    public function show(SubscriptionList $list): Response
    {
        $list->loadCount(['subscribers', 'activeSubscribers']);

        $subscribers = $list->subscribers()
            ->latest()
            ->paginate(25);

        return Inertia::render('admin/subscribers/lists/Show', [
            'list' => $list,
            'subscribers' => $subscribers,
        ]);
    }

    public function update(UpdateListRequest $request, SubscriptionList $list): RedirectResponse
    {
        $validated = $request->validated();

        if (($validated['is_default'] ?? false) && !$list->is_default) {
            SubscriptionList::where('is_default', true)->update(['is_default' => false]);
        }

        $list->update([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return back()->with('success', 'List updated successfully.');
    }

    public function destroy(SubscriptionList $list): RedirectResponse
    {
        if ($list->is_default) {
            return back()->with('error', 'Cannot delete the default list.');
        }

        $list->subscribers()->detach();
        $list->delete();

        return redirect()->route('admin.subscribers.lists.index')
            ->with('success', 'List deleted successfully.');
    }
}
