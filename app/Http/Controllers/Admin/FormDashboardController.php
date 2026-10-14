<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Services\DataScopeService;
use App\Services\FormReportService;
use App\Services\FormTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FormDashboardController extends Controller
{
    public function __construct(
        private readonly FormReportService $report,
        private readonly FormTemplateService $templates,
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $formType = FormType::selectedOrDefault($request->input('form_type'));

        $period = $request->filled('period') ? (int) $request->period : (int) now()->year;

        $filters = [
            'type' => in_array($request->type, ['employee', 'non_employee'], true) ? $request->type : null,
            'business_unit' => $request->input('business_unit'),
        ];

        $rows = $formType
            ? $this->report->adminRows($user, $formType->id, $period, $filters)
            : collect();

        $signedByTemplate = $rows
            ->where('status', FormReportService::STATUS_SIGNED)
            ->countBy('template_id');

        $pendingByTemplate = $rows
            ->whereIn('status', [FormReportService::STATUS_PENDING, FormReportService::STATUS_DRAFT])
            ->countBy('template_id');

        return Inertia::render('Admin/Forms/Dashboard', [
            'stats' => $this->report->stats($rows),
            'byBusinessUnit' => $this->byBusinessUnit($rows),
            // Counts are over the people this admin may see, not the template's
            // whole audience.
            'templates' => $this->templates
                ->visibleTo(FormTemplate::query(), $user)
                ->where('form_type_id', $formType?->id)
                ->where('period', $period)
                ->orderByDesc('is_active')
                ->orderBy('title_en')
                ->get()
                ->map(fn (FormTemplate $template) => [
                    'id' => $template->id,
                    'title' => ['en' => $template->title_en, 'id' => $template->title_id],
                    'business_units' => $template->business_units ?? [],
                    'publish_at' => $template->publish_at?->toIso8601String(),
                    'is_active' => $template->is_active,
                    'is_published' => $template->publish_at?->isPast() ?? false,
                    'signed' => $signedByTemplate[$template->id] ?? 0,
                    'pending' => $pendingByTemplate[$template->id] ?? 0,
                ]),
            'filters' => [...$filters, 'form_type' => $formType?->id, 'period' => $period],
            'formTypes' => FormType::options(),
            'periods' => $this->report->periods($formType?->id),
            'businessUnitOptions' => $this->dataScope->businessUnitOptions($user),
        ]);
    }

    /**
     * People with no business unit have no bar to fall under and are left out.
     */
    private function byBusinessUnit(Collection $rows): array
    {
        return $rows
            ->filter(fn (array $row) => filled($row['business_unit']))
            ->groupBy('business_unit')
            ->sortKeys()
            ->map(fn (Collection $group, string $unit) => [
                'business_unit' => $unit,
                ...$this->report->stats($group),
            ])
            ->values()
            ->all();
    }
}
