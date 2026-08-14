<?php

namespace App\Exports\Sheets;

use App\Models\CoiDeclaration;
use App\Models\Companies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Collection $data,
        protected ?int $period = null,
        // When set to a question key (e.g. "family_relationship"), that
        // question's repeater answers are expanded into one column per field,
        // per entry, at the far right of the sheet. Null means the export keeps
        // only the Yes/No summary column each question already gets.
        protected ?string $detailQuestionKey = null,
        // UI language the export was requested in; drives every header, status
        // value, and question/field label. Anything but "id" falls back to en.
        protected string $locale = 'en'
    ) {
        $this->locale = $this->locale === 'id' ? 'id' : 'en';
    }

    public function title(): string
    {
        return $this->label('Team History', 'Riwayat Tim');
    }

    /**
     * Pick the English or Indonesian variant of a fixed string based on the
     * requested export language. Question and field labels carry their own
     * en/id arrays and are resolved with loc() instead.
     */
    private function label(string $en, string $id): string
    {
        return $this->locale === 'id' ? $id : $en;
    }

    /**
     * Resolve an ["en" => ..., "id" => ...] label array (from config or a
     * select option) to the export language, falling back to English.
     */
    private function loc(?array $label, string $fallback = ''): string
    {
        return (string) ($label[$this->locale] ?? $label['en'] ?? $fallback);
    }

    public function array(): array
    {
        // 2025 is the historical import: submissions were scanned attachments,
        // not answered questionnaires, so the per-question columns are
        // meaningless and "submitted" means "uploaded an attachment".
        $isLegacyPeriod = $this->period === CoiDeclaration::LEGACY_PERIOD;

        $questions = $isLegacyPeriod
            ? collect()
            : collect(config('coi.questions'));

        // The one question (if any) whose details the admin asked to expand.
        // Legacy 2025 has no questionnaire, so detail expansion never applies.
        $detailQuestion = $isLegacyPeriod
            ? null
            : $questions->firstWhere('key', $this->detailQuestionKey);

        // A repeater question can hold several entries per declaration, so the
        // detail block is only as wide as the busiest declaration in this
        // export. Pre-scan to find that maximum before laying out the header.
        $maxDetailEntries = 0;

        if ($detailQuestion) {
            foreach ($this->data as $row) {
                $maxDetailEntries = max(
                    $maxDetailEntries,
                    count($this->detailEntries($row, $this->detailQuestionKey))
                );
            }
        }

        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        $headers = [

            $this->label('Employee ID', 'ID Karyawan'),

            $this->label('Employee Name', 'Nama Karyawan'),
            $this->label('Business Unit', 'Unit Bisnis'),
            $this->label('Contribution Level', 'Level Kontribusi'),
            $this->label('Work Location', 'Lokasi Kerja'),
            $this->label('Employee Status', 'Status Karyawan'),
            $this->label('Designation', 'Jabatan'),
            $this->label('Join Date', 'Tanggal Bergabung'),

            $this->label('Declaration Period', 'Periode Deklarasi'),

            $this->label('Declaration Type', 'Jenis Deklarasi'),

            $this->label('Declaration Status', 'Status Deklarasi'),

            $isLegacyPeriod
                ? $this->label('Attachment Status', 'Status Lampiran')
                : $this->label('Form Status', 'Status Formulir'),

            $this->label('Submitted At', 'Tanggal Kirim'),

        ];

        foreach ($questions as $question) {

            $headers[] = $this->loc($question['title'] ?? null, $question['key']);

        }

        // Detail columns: "<Field label> <n>" for every field of the selected
        // question, repeated for each entry group.
        for ($i = 1; $i <= $maxDetailEntries; $i++) {

            foreach ($detailQuestion['fields'] as $field) {

                $label = $this->loc($field['label'] ?? null, $field['key']);

                $headers[] = "{$label} {$i}";

            }

        }

        $rows[] = $headers;

        /*
        |--------------------------------------------------------------------------
        | Data
        |--------------------------------------------------------------------------
        */

        foreach ($this->data as $row) {

            $responseMap = collect(
                data_get(
                    $row,
                    'declaration.responses',
                    []
                )
            )->keyBy('question_key');

            if ($isLegacyPeriod) {

                $attachment = data_get(
                    $responseMap->get(
                        CoiDeclaration::LEGACY_CONFLICT_KEY
                    ),
                    'response_value.attachment'
                );

                $formStatus = filled($attachment)
                    ? $this->label('Submitted', 'Terkirim')
                    : $this->label('Not Submitted', 'Belum Dikirim');

            } else {

                $formStatus = match ($row['status']) {
                    'submitted' => $this->label('Submitted', 'Terkirim'),
                    'draft' => $this->label('Draft', 'Draf'),
                    default => $this->label('Not Submitted', 'Belum Dikirim'),
                };

            }

            $record = [

                $row['employee_id'],

                $row['name'],
                $row['group_company'],
                // Non-employees have no contribution level; leave it blank.
                $row['contribution_level'] ?? '',
                $row['office_area'],
                $row['employee_status'],
                $row['designation'],
                $row['date_of_joining'],

                $row['period'],

                $row['type'] === 'non_employee'
                    ? $this->label('Non-Employee', 'Non Karyawan')
                    : $this->label('Employee', 'Karyawan'),

                // No submission means there is nothing to judge, so the
                // declaration status stays empty rather than claiming there is
                // no conflict.
                $row['status'] === 'submitted'
                    ? ($row['has_conflict']
                        ? $this->label('Has Conflict', 'Ada Konflik')
                        : $this->label('No Potential Conflict', 'Tidak Ada Potensi Konflik'))
                    : '-',

                $formStatus,

                // submitted_at is stored in UTC (app timezone is UTC). The UI
                // renders it with new Date().getHours(), i.e. the viewer's local
                // timezone (Asia/Jakarta for KPN), so convert here too or the
                // export shows a time 7 hours behind the screen.
                $row['submitted_at']
                    ? Carbon::parse($row['submitted_at'], 'UTC')->setTimezone('Asia/Jakarta')->format('d-m-Y H:i:s')
                    : '-',

            ];

            foreach ($questions as $question) {

                $response = $responseMap->get(
                    $question['key']
                );

                $record[] = data_get(
                    $response,
                    'response_value.answer',
                    false
                )
                    ? '✓'
                    : '-';
            }

            // Detail columns for the selected question. Every row is padded to
            // $maxDetailEntries groups with blanks so the columns stay aligned
            // regardless of how many entries each declaration listed.
            if ($detailQuestion) {

                $details = data_get(
                    $responseMap->get($detailQuestion['key']),
                    'response_value.details',
                    []
                ) ?: [];

                for ($i = 0; $i < $maxDetailEntries; $i++) {

                    $detail = $details[$i] ?? null;

                    foreach ($detailQuestion['fields'] as $field) {

                        $record[] = is_array($detail)
                            ? $this->formatFieldValue($field, $detail)
                            : '';

                    }

                }

            }

            $rows[] = $record;
        }

        return $rows;
    }

    /**
     * The repeater entries a declaration listed for the given question, or an
     * empty array when it did not answer / has no submission.
     */
    private function detailEntries(mixed $row, string $questionKey): array
    {
        $response = collect(data_get($row, 'declaration.responses', []))
            ->keyBy('question_key')
            ->get($questionKey);

        return data_get($response, 'response_value.details', []) ?: [];
    }

    /**
     * Render one field of one detail entry as a single cell, mirroring how the
     * report screen (DeclarationViewModal) and the PDF display each type:
     *  - date_range: "<from> - <to>" (or "Current" for an open end);
     *  - multi-select (e.g. company): contribution_level_code values resolved to
     *    their company names, comma-joined;
     *  - select: the English option label, with any "Others" free text appended;
     *  - everything else: the raw stored value.
     */
    private function formatFieldValue(array $field, array $detail): string
    {
        $key = $field['key'];
        $type = $field['type'] ?? 'text';

        if ($type === 'date_range') {

            $from = $this->formatDate(data_get($detail, "{$key}_from"));

            $to = data_get($detail, "{$key}_current")
                ? $this->label('Current', 'Saat Ini')
                : $this->formatDate(data_get($detail, "{$key}_to"));

            if ($from === '' && $to === '') {
                return '';
            }

            return "{$from} - {$to}";
        }

        $value = data_get($detail, $key);

        // Multi-select values arrive as an array of codes.
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($code) => $this->companyNames()[$code] ?? $code)
                ->implode(', ');
        }

        if (blank($value)) {
            return '';
        }

        if ($type === 'select' && ! empty($field['options'])) {

            $option = collect($field['options'])
                ->firstWhere('value', $value);

            $label = $this->loc($option['label'] ?? null, (string) $value);

            // Options like "Others (please specify)" carry a free-text value in
            // a sibling key; append it so the specifics are not lost.
            foreach ($option['requires'] ?? [] as $required) {

                $extra = data_get($detail, $required['key']);

                if (filled($extra)) {
                    $label .= " ({$extra})";
                }

            }

            return $label;
        }

        return (string) $value;
    }

    /**
     * A stored date (yyyy-mm-dd, or yyyy-mm for month-granular ranges) as
     * d-m-Y; a value that will not parse is returned untouched rather than
     * throwing, and a blank stays blank.
     */
    private function formatDate(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * contribution_level_code => contribution_level (company/legal-entity name),
     * the same lookup the PDF and the report screen use. Loaded once per export.
     *
     * @var array<string, string>|null
     */
    private ?array $companyNames = null;

    private function companyNames(): array
    {
        return $this->companyNames ??= Companies::query()
            ->pluck('contribution_level', 'contribution_level_code')
            ->toArray();
    }
}
