<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        Conflict of Interest Declaration
    </title>

    <style>
        @page {
            margin: 18mm 16mm;
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

@include('pdf.partials.header')

@include('pdf.partials.employee')

@include('pdf.partials.questions')

@include('pdf.partials.footer')
{{-- @include('pdf.partials.consent') --}}

@include('pdf.partials.appendix')


</body>
</html>