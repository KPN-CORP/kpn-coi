<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">

    <style>

        @page{
            margin:25px;
        }

        body{
            font-family: DejaVu Sans, sans-serif;
            font-size:12px;
            color:#374151;
        }

        h1,h2,h3{
            margin:0;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        .page-break{
            page-break-after:always;
        }

    </style>

</head>

<body>

@include('pdf.dashboard.partials.header')

@include('pdf.dashboard.partials.summary')

@include('pdf.dashboard.partials.charts')

@include('pdf.dashboard.partials.business-unit')

@include('pdf.dashboard.partials.recent-submission')

@include('pdf.dashboard.partials.footer')

</body>

</html>