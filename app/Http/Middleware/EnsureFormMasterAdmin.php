<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\FormMasterAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Master Settings (form templates and form types) is open only to the
 * accounts listed in FORM_MASTER_ADMIN_EMAILS, on top of the form_master.*
 * permission each route checks -- a role alone is not enough.
 */
class EnsureFormMasterAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(FormMasterAccess::isListed($request->user()), 403);

        return $next($request);
    }
}
