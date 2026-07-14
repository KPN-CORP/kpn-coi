<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 25px 25px 60px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #374151;
        }

        h1,
        h2,
        h3 {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .page-break {
            page-break-after: always;
        }

        footer {
            position: fixed;
            bottom: -35px;
            left: 0;
            right: 0;

            height: 35px;

            font-size: 11px;
        }
    </style>
</head>

<body>

@include('pdf.dashboard.partials.header')

@include('pdf.dashboard.partials.summary')

@include('pdf.dashboard.partials.charts')

@include('pdf.dashboard.partials.business-unit')

{{-- @include('pdf.dashboard.partials.recent-submission') --}}

<footer>
    @include('pdf.dashboard.partials.footer')
</footer>

</body>

</html>