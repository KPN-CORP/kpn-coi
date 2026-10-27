<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use SoftDeletes;

    /**
     * Paper sizes offered in the editor, in millimetres (portrait). F4 is the
     * Indonesian folio (215 x 330), not dompdf's US folio.
     */
    public const PAPER_SIZES = [
        'a4' => [210, 297],
        'f4' => [215, 330],
        'letter' => [215.9, 279.4],
        'legal' => [215.9, 355.6],
    ];

    /**
     * Enough to decide which template applies and to list it, without the
     * six longText body columns. Load the full row only to render one.
     */
    public const SUMMARY_COLUMNS = [
        'id', 'form_type_id', 'period', 'period_year', 'period_month', 'title_en', 'title_id',
        'business_units', 'publish_at', 'is_active', 'updated_at',
    ];

    protected $connection = 'mysql';

    protected $table = 'form_templates';

    protected $fillable = [
        'form_type_id',
        'period_year',
        'period_month',
        'title_en',
        'title_id',
        'content_en',
        'content_id',
        'header_en',
        'header_id',
        'footer_en',
        'footer_id',
        'paper_size',
        'orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
        'font_family',
        'font_size',
        'line_height',
        'business_units',
        'publish_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'business_units' => 'array',
        'publish_at' => 'datetime',
        'is_active' => 'boolean',
        'margin_top' => 'float',
        'margin_right' => 'float',
        'margin_bottom' => 'float',
        'margin_left' => 'float',
        'font_size' => 'float',
        'line_height' => 'float',
    ];

    /**
     * YYYYMM. Computed by the database from period_year and period_month
     * (never written); worked out here too for a model not saved yet, such
     * as the template editor's preview.
     */
    protected function period(): Attribute
    {
        return Attribute::get(function ($value, array $attributes) {
            if ($value !== null) {
                return (int) $value;
            }

            return isset($attributes['period_year'], $attributes['period_month'])
                ? (int) $attributes['period_year'] * 100 + (int) $attributes['period_month']
                : null;
        });
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class)->withTrashed();
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(FormDeclaration::class, 'template_id');
    }

    public function signedDeclarations(): HasMany
    {
        return $this->declarations()->signed();
    }

    /**
     * Active, past its publish date, and of an active form type -- what an
     * employee may see and sign. Switching a form type off hides all of its
     * templates without touching them.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('publish_at', '<=', now())
            ->whereIn('form_type_id', FormType::query()->active()->select('id'));
    }

    /**
     * A template with no business units applies to every unit.
     */
    public function appliesToAllUnits(): bool
    {
        return empty($this->business_units);
    }

    public function appliesTo(?string $businessUnit): bool
    {
        return $this->appliesToAllUnits()
            || in_array($businessUnit, $this->business_units ?? [], true);
    }

    /**
     * Once anyone has signed, the wording is evidence of what they agreed to,
     * so the template can only be switched on/off -- never edited or deleted.
     */
    public function isLocked(): bool
    {
        // Drafts carry no signature, so they do not freeze the wording.
        return $this->signedDeclarations()->exists();
    }

    /**
     * Paper, margins and the default font, for the PDF and the on-screen page.
     *
     * @return array{paper_size: string, orientation: string, width: float, height: float, margin_top: float, margin_right: float, margin_bottom: float, margin_left: float, font_family: string, font_css: string, font_size: float, line_height: float}
     */
    public function pageLayout(): array
    {
        $paper = array_key_exists($this->paper_size, self::PAPER_SIZES) ? $this->paper_size : 'a4';
        [$width, $height] = self::PAPER_SIZES[$paper];
        $landscape = $this->orientation === 'landscape';

        return [
            'paper_size' => $paper,
            'orientation' => $landscape ? 'landscape' : 'portrait',
            'width' => (float) ($landscape ? $height : $width),
            'height' => (float) ($landscape ? $width : $height),
            'margin_top' => (float) ($this->margin_top ?? 30),
            'margin_right' => (float) ($this->margin_right ?? 12.7),
            'margin_bottom' => (float) ($this->margin_bottom ?? 20),
            'margin_left' => (float) ($this->margin_left ?? 12.7),
            'font_family' => self::fontKey($this->font_family),
            'font_css' => config('forms.fonts.'.self::fontKey($this->font_family).'.css'),
            'font_size' => (float) ($this->font_size ?: 10),
            'line_height' => (float) ($this->line_height ?: 1.5),
        ];
    }

    /**
     * A configured font key, falling back to the first one.
     */
    public static function fontKey(?string $key): string
    {
        $fonts = config('forms.fonts', []);

        return array_key_exists((string) $key, $fonts) ? (string) $key : (string) array_key_first($fonts);
    }

    public function title(string $locale): string
    {
        return $locale === 'id' ? $this->title_id : $this->title_en;
    }

    public function header(string $locale): string
    {
        return (string) ($locale === 'id' ? $this->header_id : $this->header_en);
    }

    public function footer(string $locale): string
    {
        return (string) ($locale === 'id' ? $this->footer_id : $this->footer_en);
    }

    public function content(string $locale): string
    {
        return $locale === 'id' ? $this->content_id : $this->content_en;
    }
}
