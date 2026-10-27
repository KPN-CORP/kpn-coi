<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\FormDeclarationImportTemplateExport;
use App\Exports\ImportLogReportExport;
use App\Http\Controllers\Controller;
use App\Imports\FormDeclarationImport;
use App\Models\FormType;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\FormDeclarationImportService;
use App\Support\FormPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import Center: bulk data loads from spreadsheets. Its first import is form
 * signatures (FormDeclarationImportService). Besides import.* a form import
 * needs that form type's own permission (form_type.{code}) -- the same one
 * that opens its report -- so an admin can only load data for forms they may
 * already see.
 *
 * Every upload is kept in the history (ImportLog): who, when, the file itself
 * and the outcome of each row. An admin with data restrictions sees only
 * their own uploads -- other admins' files and results may name people
 * outside their restrictions.
 */
class ImportCenterController extends Controller
{
    private const LAST_IMPORT_SESSION_KEY = 'import_log_id';

    public function __construct(
        private readonly FormDeclarationImportService $imports,
        private readonly DataScopeService $dataScope,
    ) {}

    public function index(Request $request): Response
    {
        $formTypes = $this->accessibleFormTypes($request->user());

        // History covers switched-off form types too.
        $historyTypeIds = $this->accessibleFormTypes($request->user(), activeOnly: false)->pluck('id')->all();

        $history = $this->visibleLogs($request->user(), $historyTypeIds)
            ->with('formType:id,name_en,name_id')
            ->latest('id')
            ->paginate(in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10)
            ->withQueryString()
            ->through(fn (ImportLog $log) => $this->summary($log));

        $lastId = $request->session()->get(self::LAST_IMPORT_SESSION_KEY);

        $last = $lastId
            ? $this->visibleLogs($request->user(), $historyTypeIds)->with('formType:id,name_en,name_id')->find($lastId)
            : null;

        return Inertia::render('Admin/ImportCenter', [
            'formTypes' => $formTypes
                ->map(fn (FormType $type) => [
                    'code' => $type->code,
                    'name' => ['en' => $type->name_en, 'id' => $type->name_id],
                    'periods' => collect($this->imports->periods($type))
                        ->map(fn (int $period) => [
                            'value' => $period,
                            'label' => ['en' => FormPeriod::label($period), 'id' => FormPeriod::label($period, 'id')],
                        ])
                        ->values(),
                ])
                ->values(),
            'history' => $history,
            'lastImport' => $last ? $this->summary($last) : null,
        ]);
    }

    public function template(Request $request, FormType $formType): BinaryFileResponse
    {
        $this->authorizeFormType($request, $formType);

        return Excel::download(
            new FormDeclarationImportTemplateExport($formType),
            'Commitment-Corner-'.Str::slug($formType->name_en).'-Import-Template.xlsx'
        );
    }

    public function import(Request $request, FormType $formType): RedirectResponse
    {
        $this->authorizeFormType($request, $formType);

        // Year and month come per row from the file; the language is the
        // importer's top-menu choice.
        $data = $request->validate([
            'locale' => ['required', Rule::in(['en', 'id'])],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $user = $request->user();

        // Kept on the private disk so the history can hand it back later.
        $log = ImportLog::query()->create([
            'type' => ImportLog::TYPE_FORM_SIGNATURES,
            'form_type_id' => $formType->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->storeAs(
                'imports/'.now()->format('Y/m'),
                Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'xlsx'),
                'local'
            ),
            'file_size' => $file->getSize(),
            'locale' => $data['locale'],
            'status' => ImportLog::STATUS_PROCESSING,
        ]);

        try {
            $reader = new FormDeclarationImport;

            Excel::import($reader, $file);

            if ($reader->rows->isEmpty()) {
                $log->update(['status' => ImportLog::STATUS_FAILED, 'message' => 'The file has no rows to import.']);
            } else {
                $log->finish($this->imports->import($reader->rows, $formType, $user, $data['locale']));
            }
        } catch (\Throwable $exception) {
            report($exception);

            $log->update([
                'status' => ImportLog::STATUS_FAILED,
                'message' => 'The file could not be read. Use the template and save it as .xlsx.',
            ]);
        }

        return back()->with(self::LAST_IMPORT_SESSION_KEY, $log->id);
    }

    /** The uploaded spreadsheet, as it was uploaded. */
    public function file(Request $request, ImportLog $importLog): StreamedResponse
    {
        $this->authorizeLog($request, $importLog);

        abort_unless(Storage::disk('local')->exists($importLog->file_path), 404);

        return Storage::disk('local')->download($importLog->file_path, $importLog->file_name);
    }

    /** Every row with its result: imported, already signed, No, or failed and why. */
    public function report(Request $request, ImportLog $importLog): BinaryFileResponse
    {
        $this->authorizeLog($request, $importLog);

        abort_if(empty($importLog->results), 404);

        return Excel::download(
            new ImportLogReportExport($importLog),
            'Import-Result-'.$importLog->created_at->format('Ymd-His').'-'.pathinfo($importLog->file_name, PATHINFO_FILENAME).'.xlsx'
        );
    }

    /** @return Collection<int, FormType> */
    private function accessibleFormTypes(User $user, bool $activeOnly = true)
    {
        return FormType::query()
            ->when($activeOnly, fn (Builder $query) => $query->active())
            ->ordered()
            ->get()
            ->filter(fn (FormType $type) => $user->can($type->permissionName()))
            ->values();
    }

    /**
     * Uploads this admin may see: for forms they can access, and only their
     * own when their role carries data restrictions.
     *
     * @param  list<int>  $formTypeIds
     */
    private function visibleLogs(User $user, array $formTypeIds): Builder
    {
        return ImportLog::query()
            ->where('type', ImportLog::TYPE_FORM_SIGNATURES)
            ->whereIn('form_type_id', $formTypeIds)
            ->when($this->dataScope->isRestricted($user), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /** A history row for the page -- never the per-row results. */
    private function summary(ImportLog $log): array
    {
        return [
            'id' => $log->id,
            'form' => $log->formType
                ? ['en' => $log->formType->name_en, 'id' => $log->formType->name_id]
                : null,
            'file_name' => $log->file_name,
            'file_size' => $log->file_size,
            'user_name' => $log->user_name,
            'created_at' => $log->created_at?->toIso8601String(),
            'status' => $log->status,
            'message' => $log->message,
            'total' => $log->total_rows,
            'imported' => $log->imported_count,
            'already_signed' => $log->already_signed_count,
            'not_signed' => $log->not_signed_count,
            'failed' => $log->failed_count,
            'has_report' => $log->total_rows > 0,
        ];
    }

    private function authorizeFormType(Request $request, FormType $formType): void
    {
        abort_unless($formType->is_active, 404);
        abort_unless($request->user()?->can($formType->permissionName()), 403);
    }

    private function authorizeLog(Request $request, ImportLog $log): void
    {
        $user = $request->user();

        abort_unless(
            $this->visibleLogs($user, $this->accessibleFormTypes($user, activeOnly: false)->pluck('id')->all())
                ->whereKey($log->id)
                ->exists(),
            404
        );
    }
}
