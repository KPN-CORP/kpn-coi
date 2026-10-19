<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormDeclaration;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed document as a PDF -- one implementation for the employee's own
 * download and the admin report, so both print exactly the same thing.
 */
class FormPdfService
{
    public function __construct(
        private readonly FormDeclarationService $forms,
        private readonly FormImageService $images,
    ) {}

    public function download(FormDeclaration $declaration, ?string $locale): Response
    {
        // A draft has no signature or snapshot to print.
        abort_unless($declaration->isSigned(), 404);

        if (! in_array($locale, ['en', 'id'], true)) {
            $locale = $declaration->locale ?: 'en';
        }

        $document = $this->forms->renderSigned($declaration, $locale);

        $page = $declaration->template->pageLayout();

        // dompdf cannot follow the session-protected image route, so images
        // are inlined. Remote fetching is on only for the signature's
        // handwriting font (Ephesis, as on the COI PDF), and only from
        // Google Fonts -- template HTML can never make dompdf fetch elsewhere.
        $pdf = Pdf::loadView('pdf.form_declaration', [
            'declaration' => $declaration,
            'title' => $document['title'],
            'content' => $this->images->inlineForPdf($document['content']),
            'header' => $this->images->inlineForPdf($document['header']),
            'footer' => $this->images->inlineForPdf($document['footer']),
            'page' => $page,
            'locale' => $locale,
            // Replaced by a newer version: still printable, marked not valid.
            'superseded' => $this->forms->isSuperseded($declaration),
        ])->setOptions([
            'isRemoteEnabled' => true,
            'allowedRemoteHosts' => ['fonts.googleapis.com', 'fonts.gstatic.com'],
            'isHtml5ParserEnabled' => true,
        ])->setPaper([0, 0, self::mmToPt($page['width']), self::mmToPt($page['height'])]);

        $slug = fn (string $value) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-');

        return $pdf->download(sprintf(
            '%s-%s-%s-%s.pdf',
            $slug($declaration->formType?->name($locale) ?? 'Form'),
            $declaration->period,
            $slug((string) data_get($declaration->variables, 'employee_name', 'signer')),
            strtoupper($locale)
        ));
    }

    private static function mmToPt(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
