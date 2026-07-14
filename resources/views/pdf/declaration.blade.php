<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        Conflict of Interest Declaration
    </title>

    <style>
        @page {
            margin: 18mm 16mm 20mm 16mm;
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
    $footerText = $locale === 'id'
        ? 'Dideklarasikan oleh '.$declaration->user->employee->fullname.' pada '.$declaration->created_at->translatedFormat('d F Y \p\u\k\u\l H:i:s')
        : 'Declared by '.$declaration->user->employee->fullname.' on '.$declaration->created_at->format('d/m/Y \a\t H:i:s');
@endphp

<script type="text/php">
    if (isset($pdf)) {

        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');

        $pdf->page_text(
            120,
            $pdf->get_height() - 18,
            "{{ $footerText }}",
            $font,
            9,
            [0, 0, 0]
        );

        $pdf->page_text(
            535,
            $pdf->get_height() - 18,
            "Page {PAGE_NUM} of {PAGE_COUNT}",
            $font,
            9,
            [0, 0, 0]
        );

    }
</script>

@include('pdf.partials.appendix')

{{-- @include('pdf.partials.footer') --}}

</body>
</html>