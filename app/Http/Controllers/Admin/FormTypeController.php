<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFormTypeRequest;
use App\Models\AppSetting;
use App\Models\FormType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Master Settings -> Form Types. Each active type becomes an employee menu
 * item; switching one off hides its menu and stops new signatures while
 * keeping everything already signed.
 */
class FormTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Forms/FormTypes', [
            'formTypes' => FormType::query()
                ->ordered()
                ->withCount(['templates', 'signedDeclarations'])
                ->get()
                ->map(fn (FormType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name_en' => $type->name_en,
                    'name_id' => $type->name_id,
                    'description_en' => $type->description_en,
                    'description_id' => $type->description_id,
                    'icon' => $type->icon,
                    'is_active' => $type->is_active,
                    'sort_order' => $type->sort_order,
                    'templates_count' => $type->templates_count,
                    'declarations_count' => $type->signed_declarations_count,
                ]),
            'icons' => SaveFormTypeRequest::ICONS,
            // Conflict of Interest is not a form type, but takes its place in
            // the same menu order (AppSetting::COI_MENU_ORDER).
            'coiSortOrder' => AppSetting::coiMenuOrder(),
        ]);
    }

    /**
     * Move Conflict of Interest in the employee menu, among the form types.
     */
    public function updateCoiOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        AppSetting::put(AppSetting::COI_MENU_ORDER, (string) $data['sort_order']);

        return back()->with('success', 'Menu order updated.');
    }

    public function store(SaveFormTypeRequest $request): RedirectResponse
    {
        $formType = FormType::create([
            ...$this->attributes($request),
            'code' => $this->uniqueCode($request->string('name_en')->toString()),
        ]);

        // So Role Management can grant its dashboard/report right away.
        $formType->ensurePermission();

        return back()->with('success', 'Form type created.');
    }

    /**
     * The code is not editable: it is the employee page URL.
     */
    public function update(SaveFormTypeRequest $request, FormType $formType): RedirectResponse
    {
        $formType->update($this->attributes($request));

        return back()->with('success', 'Form type updated.');
    }

    public function destroy(FormType $formType): RedirectResponse
    {
        if ($formType->templates()->withTrashed()->exists()) {
            return back()->with(
                'error',
                'This form type still has templates. Delete them first, or deactivate the form type instead.'
            );
        }

        $formType->delete();

        Permission::query()
            ->where('name', $formType->permissionName())
            ->where('guard_name', 'web')
            ->get()
            ->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Form type deleted.');
    }

    private function attributes(SaveFormTypeRequest $request): array
    {
        return [
            'name_en' => trim($request->string('name_en')->toString()),
            'name_id' => trim($request->string('name_id')->toString()),
            'description_en' => $request->input('description_en') ?: null,
            'description_id' => $request->input('description_id') ?: null,
            'icon' => $request->input('icon'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    /**
     * URL slug from the English name; soft-deleted codes count as taken so
     * an old link never starts pointing at a different form.
     */
    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        $base = Str::limit($base, 40, '');
        $code = $base;
        $suffix = 2;

        while (FormType::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
