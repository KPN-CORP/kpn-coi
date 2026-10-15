<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">

    <title>{{ $title }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Ephesis&display=swap" rel="stylesheet">

@php
    // Header/footer sit inside the top/bottom margins, a fixed distance from
    // the paper edge (Word's default is ~12.5mm; smaller when the margin is
    // too shallow for that). dompdf positions fixed elements relative to the
    // body box, hence the negative offsets.
    $headerGap = min(10, $page['margin_top'] / 3);
    $footerGap = min(8, $page['margin_bottom'] / 3);
@endphp

    <style>
        @page {
            margin: {{ $page['margin_top'] }}mm {{ $page['margin_right'] }}mm {{ $page['margin_bottom'] }}mm {{ $page['margin_left'] }}mm;
        }

        #form-header {
            position: fixed;
            top: -{{ $page['margin_top'] - $headerGap }}mm;
            left: 0;
            right: 0;
        }

        #form-footer {
            position: fixed;
            bottom: -{{ $page['margin_bottom'] - $footerGap }}mm;
            left: 0;
            right: 0;
            font-size: 0.8em;
        }

        img {
            max-width: 100%;
        }

        /* The template's default font. font_css comes from config/forms.php
           (never from input), so it is printed raw -- escaping would turn its
           quotes into entities and break the CSS. */
        body {
            font-family: {!! $page['font_css'] !!};
            font-size: {{ $page['font_size'] }}pt;
            color: #000;
            line-height: {{ $page['line_height'] }};
        }


        /* Spacing and sizes mirror .form-content in resources/css/coi.css
           (what the signer read on screen): blocks 9.6px apart (0.6rem
           there), headings in em of the template's base size. Change both
           together. */
        .content > * {
            margin: 0;
        }

        .content > * + * {
            margin-top: 9.6px;
        }

        .content h1 { font-size: 1.4em; }
        .content h2 { font-size: 1.2em; }
        .content h3 { font-size: 1.1em; }
        .content h4 { font-size: 1em; }

        .content ul,
        .content ol {
            padding-left: 22px;
        }

        /* Nested numbered lists read 1. / a. / i., as in the HC forms. */
        .content ol ol { list-style-type: lower-alpha; }
        .content ol ol ol { list-style-type: lower-roman; }

        .content li + li {
            margin-top: 4px;
        }

        .content li p {
            margin: 0;
        }

        .content blockquote {
            padding-left: 10px;
            border-left: 2px solid #999;
        }

        /* Auto layout on purpose: dompdf ignores cell widths under
           table-layout: fixed, so column widths set in the editor would be
           lost. An inline width from the editor still overrides the 100%. */
        .content table {
            width: 100%;
            border-collapse: collapse;
        }

        .content td,
        .content th {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }

        .content td p,
        .content th p {
            margin: 0;
        }

        .content table[data-borderless] td,
        .content table[data-borderless] th {
            border: 0;
            background: none;
        }

        /* Tab key gap, same width as on screen. */
        .content span[data-tab] {
            display: inline-block;
            width: 12.7mm;
        }

        .content th {
            background: #f1f5f9;
            font-weight: bold;
            text-align: left;
        }

        .content hr {
            border: 0;
            border-top: 1px solid #000;
            margin: 10px 0;
        }

        /* The signature variable -- same look as the COI declaration PDF
           (pdf/partials/consent.blade.php). Mirrored in coi.css. */
        .form-signature-name {
            font-family: "Ephesis", cursive;
            font-weight: normal;
            font-style: normal;
            font-size: 22pt;
            line-height: 1.2;
        }
    </style>
</head>
<body>
    {{-- Fixed elements must come before the body to repeat on every page. --}}
    @if (filled(strip_tags($header, '<img>')))
        <div id="form-header" class="content">{!! $header !!}</div>
    @endif

    @if (filled(strip_tags($footer, '<img>')))
        <div id="form-footer" class="content">{!! $footer !!}</div>
    @endif

    {{-- The template name is not printed: any heading belongs to the body,
         where the author can format it. $title only names the PDF file. --}}
    {{-- Sanitised on save (FormHtmlSanitizer) and variables escaped on
         render (FormVariableService), so it is printed unescaped. --}}
    <div class="content">{!! $content !!}</div>

    {{-- Nothing is printed beyond the template: no signature block (the
         template lays out its own with the signature / sign_date /
         employee_name variables) and no period / document number line. --}}
</body>
</html>
