<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin console guard: the `subscribe.admin` gate decides, and anyone it
 * refuses gets a 404, so the console's existence is not advertised.
 */
final class AuthorizeAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('subscribe.admin'), 404);

        return $next($request);
    }
}
