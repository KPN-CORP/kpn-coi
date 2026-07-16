<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        Conflict of Interest Declaration
    </title>

    <style>
        @page {
            margin: 18mm 16mm 40mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #000;
            line-height: 1.25;
        }

        h1,
        h2,
        h3,
        h4,
        p {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .border {
            border: 1px solid #000;
        }

        .border td,
        .border th {
            border: 1px solid #000;
            vertical-align: top;
            padding: 6px;
        }

        .title {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .title-2 {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .mt-1 { margin-top:5px; }
        .mt-2 { margin-top:10px; }
        .mt-3 { margin-top:15px; }
        .mt-4 { margin-top:20px; }

        .text-center {
            text-align:center;
        }

        .bold {
            font-weight:bold;
        }

        .page-break{
            page-break-before:always;
        }

        .signature{
            margin-top:60px;
        }

        .footnote{
            font-size:8pt;
            margin-top:25px;
        }
        .field-table {
            width: 100%;
            border-collapse: collapse;
        }

        .field-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
    </style>

</head>

<body>

{{-- @php
    $path = public_path('storage/images/letterhead.png');
    $src = null;

    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $src = 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($path));
    }
@endphp

@if($src)
    <img src="{{ $src }}" style="top: -300px;left: -16px;width:320px; height:auto;">
@endif --}}

@include('pdf.partials.header')

@include('pdf.partials.employee')

@include('pdf.partials.questions')

<div style="page-break-before: always;"></div>

@include('pdf.partials.consent')

@php
    $date = $declaration->created_at
        ->copy()
        ->timezone('Asia/Jakarta')
        ->locale($locale === 'id' ? 'id' : 'en');

    $footerText = $locale === 'id'
        ? 'Dideklarasikan oleh '
            . $declaration->user->employee->fullname
            . ' pada '
            . $date->translatedFormat('d F Y')
            . ' pukul '
            . $date->format('H:i:s')
        : 'Declared by '
            . $declaration->user->employee->fullname
            . ' on '
            . $date->translatedFormat('d F Y')
            . ' at '
            . $date->format('H:i:s');

    $pageText = $locale === 'id'
        ? 'Halaman {PAGE_NUM} dari {PAGE_COUNT}'
        : 'Page {PAGE_NUM} of {PAGE_COUNT}';

    $pageTextAlign = $locale === 'id'
        ? 510
        : 535;

    $formNumber = '003.FORM.CHC.III.2025';

    $formNumberLabel = $locale === 'id' ? 'Nomor Form' : 'Form Number';

    $notes = $locale === 'id'
        ? [
            'Pilih `Ya` pada jawaban yang sesuai.',
            'Keluarga Inti adalah pasangan (suami/istri), orang tua, mertua, anak atau menantu yang terdaftar dalam dokumen kependudukan resmi yang dikeluarkan oleh negara.',
            'Hubungan kekerabatan dalam 1 (satu) garis silsilah keluarga dan melibatkan 2 (dua) generasi yang dihitung mulai dari diri sendiri ke 2 (dua) generasi di atasnya (orang tua, mertua, kakek nenek dari diri sendiri dan pasangan), 2 (dua) generasi di bawahnya (anak, cucu) dan generasi setingkat (saudara kandung, pasangan).',
        ]
        : [
            'Choose `Yes` on the appropriate answer.',
            'The Immediate Family consists of the spouse (husband/wife), parents, parents-in-law, children, or children-in-law registered in the official civil documents issued by the state.',
            'Kinship relationship within 1 (one) family lineage and involving 2 (two) generations, calculated from oneself to 2 (two) generations above (parents, parents-in-law, grandparents of oneself and spouse), 2 (two) generations below (children, grandchildren), and the same generation (siblings, spouse).',
        ];

    // Pre-wrap the notes into single lines so they can be drawn (and repeated
    // on every page) via page_text, just above the footer line.
    $noteLines = [];

    foreach ($notes as $index => $note) {
        $wrapped = wordwrap(($index + 1) . ') ' . $note, 140, "\n", true);

        foreach (explode("\n", $wrapped) as $line) {
            $noteLines[] = $line;
        }
    }

    $noteLines[] = $formNumberLabel . ': ' . $formNumber;
@endphp

<script type="text/php">
    if (isset($pdf)) {

        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        // Footnotes + form number, repeated on every page above the footer.
        @foreach($noteLines as $noteIndex => $noteLine)
        $pdf->page_text(
            45,
            $pdf->get_height() - {{ 25 + (count($noteLines) - $noteIndex) * 9 }},
            "{{ addslashes($noteLine) }}",
            $font,
            7,
            [0, 0, 0]
        );
        @endforeach

        $pdf->page_text(
            120,
            $pdf->get_height() - 18,
            "{{ $footerText }}",
            $font,
            8,
            [0, 0, 0]
        );

        $pdf->page_text(
            "{{ $pageTextAlign }}",
            $pdf->get_height() - 18,
            "{{ $pageText }}",
            $font,
            8,
            [0, 0, 0]
        );

    }
</script>

<!-- disable apendix -->
<!-- @include('pdf.partials.appendix') -->

{{-- @include('pdf.partials.footer') --}}

</body>
</html>