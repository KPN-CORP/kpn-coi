<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReportDownload extends Model
{
    protected $connection = 'mysql';

    protected $table = 'report_downloads';

    protected $fillable = [
        'user_id',
        'filters',
        'status',
        'file_path',
        'file_name',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Remove the stored file (if any) and delete the record.
     */
    public function purge(): void
    {
        if (
            $this->file_path
            && Storage::disk('local')->exists($this->file_path)
        ) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->delete();
    }
}
