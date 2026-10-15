<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\FormType;
use App\Services\FormDeclarationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

                // One employee menu item per active form type, each with the
                // number of forms waiting for this account's signature.
                //
                // The account is resolved inside the closure, not taken from
                // $user above: share() runs before the route's
                // auth:web,non_employee middleware has picked the guard, so
                // $request->user() is still null for a non-employee session.
                // The closure is evaluated after the route ran.
                //
                // Migrations are applied by hand on deploy, so missing form
                // tables must not take every page down with them.
                'forms' => function () {
                    $account = Auth::guard('web')->user() ?? Auth::guard('non_employee')->user();

                    if (! $account) {
                        return [];
                    }

                    try {
                        $pending = app(FormDeclarationService::class)->pendingCounts($account);

                        return FormType::query()
                            ->active()
                            ->ordered()
                            ->get()
                            ->map(fn (FormType $type) => [
                                ...$type->summary(),
                                'pending' => $pending[$type->id] ?? 0,
                            ])
                            ->all();
                    } catch (QueryException $exception) {
                        // Logged, not swallowed silently: this also catches a
                        // kpncorp outage or a broken query, not only missing
                        // tables.
                        report($exception);

                        return [];
                    }
                },
            ],

            'role' => $user && method_exists($user, 'getRoleNames')
                ? $user->getRoleNames()->first()
                : null,

            'permissions' => $user && method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()->pluck('name')->values()
                : [],

            'coiQuestions' => fn () => config('coi.questions'),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
