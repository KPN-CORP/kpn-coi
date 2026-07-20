<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\CoiDeclaration;
use App\Models\CoiResponse;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supporting-document upload for the 2025 historical import: employees who
 * declared a conflict ("Yes") attach their PDF/DOC here. The file path is
 * kept inside the 2025 response_value (no dedicated column).
 */
class DeclarationAttachmentController extends Controller
{
    private const DISK = 'local';

    public function store(Request $request, CoiDeclaration $declaration): RedirectResponse
    {
        $response = $this->authorizeLegacyRow($request, $declaration);

        $request->validate([
            // extensions (not mimes): .doc/.docx MIME detection is unreliable,
            // so validate by the file's actual extension instead. 5 MB cap.
            'attachment' => ['required', 'file', 'extensions:pdf,doc,docx', 'max:5120'],
        ]);

        $file = $request->file('attachment');

        $employeeId = Employee::find($declaration->user_id)?->employee_id
            ?? (string) $declaration->user_id;

        $filename = "{$declaration->period}_{$employeeId}_COI." . $file->getClientOriginalExtension();

        $value = $response->response_value ?? [];

        // Re-upload replaces: remove the previous file (its extension may differ
        // from the new one, so overwriting by name is not enough).
        $previous = $value['attachment'] ?? null;

        if ($previous && Storage::disk(self::DISK)->exists($previous)) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $path = $file->storeAs((string) $declaration->period, $filename, self::DISK);

        $value['attachment'] = $path;
        $response->response_value = $value;
        $response->save();

        // Uploading the document is what "submitting" means for an imported
        // 2025 row -- until now it had no real timestamps (created_at was just
        // the import moment). Stamp both with the upload time so the history
        // table shows when the employee actually completed it.
        $uploadedAt = now();

        $declaration->submitted_at = $uploadedAt;
        $declaration->created_at = $uploadedAt;
        $declaration->save();

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function show(Request $request, CoiDeclaration $declaration): StreamedResponse
    {
        $response = $this->authorizeLegacyRow($request, $declaration);

        $path = $response->response_value['attachment'] ?? null;

        abort_if(! $path || ! Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->download($path);
    }

    /**
     * The attachment belongs to a 2025 employee declaration owned by the
     * signed-in employee -- conflict or not, every 2025 row takes a document.
     *
     * Both users tables share ids across the two databases, so the instanceof
     * check matters: it stops a non-employee whose local id collides with an
     * employee id from reaching another person's row.
     */
    private function authorizeLegacyRow(Request $request, CoiDeclaration $declaration): CoiResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        abort_unless(
            $declaration->type === 'employee'
                && (int) $declaration->period === CoiDeclaration::LEGACY_PERIOD
                && (int) $declaration->user_id === (int) $user->id,
            403
        );

        $response = $declaration->responses()
            ->where('question_key', CoiDeclaration::LEGACY_CONFLICT_KEY)
            ->first();

        abort_unless($response !== null, 403);

        return $response;
    }
}
