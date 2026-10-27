<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Import Center upload: who, when, the stored file (private `local`
 * disk) and the outcome of every row in `results`.
 */
class ImportLog extends Model
{
    public const TYPE_FORM_SIGNATURES = 'form_signatures';

    /** Still running -- or the request died part-way. */
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    /** Some rows imported, some failed. */
    public const STATUS_PARTIAL = 'partial';

    /** Nothing imported: every row failed, or the file could not be read. */
    public const STATUS_FAILED = 'failed';

    public const ROW_IMPORTED = 'imported';

    public const ROW_ALREADY_SIGNED = 'already_signed';

    public const ROW_NOT_SIGNED = 'not_signed';

    public const ROW_FAILED = 'failed';

    protected $connection = 'mysql';

    protected $fillable = [
        'type',
        'form_type_id',
        'user_id',
        'user_name',
        'file_name',
        'file_path',
        'file_size',
        'locale',
        'status',
        'total_rows',
        'imported_count',
        'already_signed_count',
        'not_signed_count',
        'failed_count',
        'message',
        'results',
    ];

    protected $casts = [
        'results' => 'array',
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'imported_count' => 'integer',
        'already_signed_count' => 'integer',
        'not_signed_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class)->withTrashed();
    }

    /**
     * Store the per-row results and the counts and status they add up to.
     *
     * @param  list<array{status: string}>  $results
     */
    public function finish(array $results): void
    {
        $count = fn (string $status) => count(array_filter($results, fn (array $row) => $row['status'] === $status));

        $failed = $count(self::ROW_FAILED);

        $this->fill([
            'results' => $results,
            'total_rows' => count($results),
            'imported_count' => $count(self::ROW_IMPORTED),
            'already_signed_count' => $count(self::ROW_ALREADY_SIGNED),
            'not_signed_count' => $count(self::ROW_NOT_SIGNED),
            'failed_count' => $failed,
            'status' => match (true) {
                $failed === 0 => self::STATUS_COMPLETED,
                $failed === count($results) => self::STATUS_FAILED,
                default => self::STATUS_PARTIAL,
            },
        ])->save();
    }
}
