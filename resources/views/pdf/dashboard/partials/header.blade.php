<table style="margin-bottom:25px;">

    <tr>

        <td width="70%">

            <h2>
                CONFLICT OF INTEREST
            </h2>

            <h1>
                Compliance Dashboard
            </h1>

        </td>

        <td align="right">

            <strong>Reporting Period</strong>

            <br>

            {{ $filters['period'] ?? 'All Periods' }}

            <br><br>

            <strong>Generated</strong>

            <br>

            {{ now()->format('d M Y H:i') }}

        </td>

    </tr>

</table>

<hr>