<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Support\FormPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Form PDFs -- one implementation for the employee's own download, the admin
 * report and the template editor's preview, so all three print exactly the
 * same thing.
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

        $pdf = $this->build(
            $declaration->template,
            $this->forms->renderSigned($declaration, $locale),
            $locale
        );

        $slug = fn (string $value) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-');

        return $pdf->download(sprintf(
            '%s-%s-%s-%s.pdf',
            $slug($declaration->formType?->name($locale) ?? 'Form'),
            sprintf('%04d-%02d', FormPeriod::year((int) $declaration->period), FormPeriod::month((int) $declaration->period)),
            $slug((string) data_get($declaration->variables, 'employee_name', 'signer')),
            strtoupper($locale)
        ));
    }

    /**
     * A template -- possibly unsaved, straight from the editor -- filled with
     * the given values and shown inline, page by page as it will print.
     *
     * @param  array<string, string|null>  $values  as FormVariableService::resolve() gives, plus signed_name
     */
    public function preview(FormTemplate $template, array $values, string $locale): Response
    {
        return $this->build($template, $this->forms->render($template, $values, $locale), $locale)
            ->stream('preview.pdf');
    }

    /**
     * @param  array{title: string, content: string, header: string, footer: string}  $document
     */
    private function build(FormTemplate $template, array $document, string $locale): DomPdf
    {
        $page = $template->pageLayout();

        $parts = [
            'content' => $this->images->inlineForPdf($document['content']),
            'header' => $this->images->inlineForPdf($document['header']),
            'footer' => $this->images->inlineForPdf($document['footer']),
        ];

        // dompdf cannot follow the session-protected image route, so images
        // are inlined. Remote fetching is on only for the signature's
        // handwriting font (Ephesis, as on the COI PDF), and only from
        // Google Fonts -- template HTML can never make dompdf fetch elsewhere.
        $make = fn (?int $pageCount) => Pdf::loadView('pdf.form_declaration', [
            'title' => $document['title'],
            ...$parts,
            'page' => $page,
            'locale' => $locale,
            'pageCount' => $pageCount,
        ])->setOptions([
            'isRemoteEnabled' => true,
            'allowedRemoteHosts' => ['fonts.googleapis.com', 'fonts.gstatic.com'],
            'isHtml5ParserEnabled' => true,
        ])->setPaper([0, 0, self::mmToPt($page['width']), self::mmToPt($page['height'])]);

        $pdf = $make(null);

        // {{page_count}}: dompdf fills the page number per page (CSS counter)
        // but has no total-pages counter. So when the template prints the
        // total, render once to count the pages, then again with the number.
        // The count cannot change in between: the total is the last field
        // rendered and printed at the same width either way.
        if (str_contains(implode('', $parts), FormVariableService::PAGING['page_count'])) {
            $pdf->render();

            $pdf = $make($pdf->getDomPDF()->getCanvas()->get_page_count());
        }

        return $pdf;
    }

    private static function mmToPt(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
