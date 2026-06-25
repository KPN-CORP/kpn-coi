<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Employee;

class EnsureManager
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        $isManager = Employee::query()
            ->where('manager_l1_id', $user->employee_id)
            ->orWhere('manager_l2_id', $user->employee_id)
            ->exists();

        abort_unless($isManager, 403);

        return $next($request);
    }
}