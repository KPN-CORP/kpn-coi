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
        protected ?int $period = null
    ) {}

    public function title(): string
    {
        return 'Team History';
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

        // Question 5 (family relationship) is a repeater -- one declaration can
        // list several family members. Instead of the single Yes/No column the
        // other questions get, every family member is spread across its own
        // group of columns (relationship / name / working area) at the far
        // right, and the sheet grows as wide as the busiest declaration.
        $familyQuestion = $questions->firstWhere('key', 'family_relationship');

        $maxFamilyEntries = 0;

        if ($familyQuestion) {
            foreach ($this->data as $row) {
                $maxFamilyEntries = max(
                    $maxFamilyEntries,
                    count($this->familyDetails($row))
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

            'Employee ID',

            'Employee Name',
            'Business Unit',
            'Contribution Level',
            'Work Location',
            'Employee Status',
            'Designation',
            'Join Date',

            'Declaration Period',

            'Declaration Type',

            'Declaration Status',

            $isLegacyPeriod
                ? 'Attachment Status'
                : 'Form Status',

            'Submitted At',

        ];

        foreach ($questions as $question) {

            $headers[] = $question['title']['en'];

        }

        for ($i = 1; $i <= $maxFamilyEntries; $i++) {

            $headers[] = "Family Relationship {$i}";
            $headers[] = "Name of Family Member {$i}";
            $headers[] = "Working Area {$i}";

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
                    ? 'Submitted'
                    : 'Not Submitted';

            } else {

                $formStatus = match ($row['status']) {
                    'submitted' => 'Submitted',
                    'draft' => 'Draft',
                    default => 'Not Submitted',
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
                    ? 'Non-Employee'
                    : 'Employee',

                // No submission means there is nothing to judge, so the
                // declaration status stays empty rather than claiming there is
                // no conflict.
                $row['status'] === 'submitted'
                    ? ($row['has_conflict'] ? 'Has Conflict' : 'No Potential Conflict')
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

            // Family relationship detail columns (see header note). Every row is
            // padded to $maxFamilyEntries groups with blanks so the columns stay
            // aligned regardless of how many members each declaration listed.
            $familyDetails = data_get(
                $responseMap->get('family_relationship'),
                'response_value.details',
                []
            ) ?: [];

            for ($i = 0; $i < $maxFamilyEntries; $i++) {

                $detail = $familyDetails[$i] ?? null;

                $record[] = $detail
                    ? $this->relationshipLabel($familyQuestion, $detail)
                    : '';

                $record[] = $detail
                    ? (string) data_get($detail, 'family_name', '')
                    : '';

                $record[] = $detail
                    ? $this->workingArea($detail)
                    : '';
            }

            $rows[] = $record;
        }

        return $rows;
    }

    /**
     * The family-member entries a declaration listed for question 5, or an
     * empty array when it did not answer / has no submission.
     */
    private function familyDetails(mixed $row): array
    {
        $response = collect(data_get($row, 'declaration.responses', []))
            ->keyBy('question_key')
            ->get('family_relationship');

        return data_get($response, 'response_value.details', []) ?: [];
    }

    /**
     * Turn a stored relationship code (e.g. "father") into its English label.
     * "Others" carries a free-text value, which we prefer over the generic
     * label so the actual relationship is not lost.
     */
    private function relationshipLabel(array $question, array $detail): string
    {
        $value = data_get($detail, 'relationship');

        if (blank($value)) {
            return '';
        }

        if ($value === 'others') {
            return (string) (data_get($detail, 'others') ?: 'Others');
        }

        $field = collect($question['fields'])
            ->firstWhere('key', 'relationship');

        $option = collect($field['options'] ?? [])
            ->firstWhere('value', $value);

        return (string) ($option['label']['en'] ?? $value);
    }

    /**
     * The family member's working area as a single cell:
     * "Business Unit - Company - Division - Position". Company is a multi-select
     * of contribution_level_code values resolved to their names; empty parts are
     * dropped so the cell never reads " -  - x".
     */
    private function workingArea(array $detail): string
    {
        $company = data_get($detail, 'company');

        $company = is_array($company)
            ? collect($company)
                ->map(fn ($code) => $this->companyNames()[$code] ?? $code)
                ->implode(', ')
            : (string) $company;

        return collect([
            data_get($detail, 'business_unit'),
            $company,
            data_get($detail, 'department'),
            data_get($detail, 'position'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->implode(' - ');
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
