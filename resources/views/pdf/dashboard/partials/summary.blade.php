<table style="margin-top:20px;margin-bottom:25px;">

    <tr>

        <td width="25%">

            <table border="1" cellpadding="10">

                <tr>

                    <td align="center">

                        <strong>Total Employee</strong>

                        <br><br>

                        <span style="font-size:28px;">
                            {{ $stats['total'] }}
                        </span>

                    </td>

                </tr>

            </table>

        </td>

        <td width="25%">

            <table border="1" cellpadding="10">

                <tr>

                    <td align="center">

                        <strong>Submitted</strong>

                        <br><br>

                        <span style="font-size:28px;color:green;">
                            {{ $stats['submitted'] }}
                        </span>

                    </td>

                </tr>

            </table>

        </td>

        <td width="25%">

            <table border="1" cellpadding="10">

                <tr>

                    <td align="center">

                        <strong>Not Submitted</strong>

                        <br><br>

                        <span style="font-size:28px;color:orange;">
                            {{ $stats['pending'] }}
                        </span>

                    </td>

                </tr>

            </table>

        </td>

        <td width="25%">

            <table border="1" cellpadding="10">

                <tr>

                    <td align="center">

                        <strong>Conflict</strong>

                        <br><br>

                        <span style="font-size:28px;color:red;">
                            {{ $stats['conflict'] }}
                        </span>

                    </td>

                </tr>

            </table>

        </td>

    </tr>

</table>