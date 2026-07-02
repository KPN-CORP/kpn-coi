<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $isManager = false;

        if ($user) {
            $isManager = Employee::query()
                ->where('manager_l1_id', $user->employee_id)
                ->orWhere('manager_l2_id', $user->employee_id)
                ->exists();
        }

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user,
            ],

            'navigation' => [
                'is_manager' => $request->user()
                    ? $isManager
                    : false,
            ],

            'role' => $user && method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->first()
                : null,

            'permissions' => $user && method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()->pluck('name')->values()
                : [],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
