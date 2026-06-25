<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoiResponse extends Model
{
    protected $fillable = [
        'coi_declaration_id',
        'question_key',
        'response_value',
    ];

    protected $casts = [
        'response_value' => 'array',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(
            CoiDeclaration::class,
            'coi_declaration_id'
        );
    }
}