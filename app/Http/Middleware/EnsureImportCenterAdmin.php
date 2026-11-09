<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ImportCenterAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Import Center is open only to the accounts listed in
 * IMPORT_CENTER_ADMIN_EMAILS, on top of the import.* permission each route
 * checks -- a role alone is not enough.
 */
class EnsureImportCenterAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ImportCenterAccess::isListed($request->user()), 403);

        return $next($request);
    }
}
