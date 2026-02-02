<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Nejcc\Subscribe\Models\Subscriber;
use Nejcc\Subscribe\Models\SubscriptionList;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalSubscribers = Subscriber::count();
        $activeSubscribers = Subscriber::subscribed()->count();
        $pendingSubscribers = Subscriber::pending()->count();
        $unsubscribedCount = Subscriber::unsubscribed()->count();
        $totalLists = SubscriptionList::count();

        $recentSubscribers = Subscriber::latest()
            ->limit(10)
            ->get(['id', 'email', 'first_name', 'last_name', 'status', 'created_at']);

        $lists = SubscriptionList::withCount('subscribers')
            ->orderBy('subscribers_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'is_default']);

        $driver = config('database.default');
        $dateFormat = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyGrowth = Subscriber::selectRaw("{$dateFormat} as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw($dateFormat)
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        return Inertia::render('admin/subscribers/Dashboard', [
            'stats' => [
                'total' => $totalSubscribers,
                'active' => $activeSubscribers,
                'pending' => $pendingSubscribers,
                'unsubscribed' => $unsubscribedCount,
                'lists' => $totalLists,
            ],
            'recentSubscribers' => $recentSubscribers,
            'topLists' => $lists,
            'monthlyGrowth' => $monthlyGrowth,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Subscriber::count(),
            'active' => Subscriber::subscribed()->count(),
            'pending' => Subscriber::pending()->count(),
            'unsubscribed' => Subscriber::unsubscribed()->count(),
        ]);
    }
}
