@php
    // Form types reuse the COI card slots: submitted/pending are signed/not
    // signed, and the fourth card is drafts instead of conflicts.
    $isForm = ($formKind ?? 'coi') === 'form';
@endphp
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

                        <strong>{{ $isForm ? 'Signed' : 'Submitted' }}</strong>

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

                        <strong>{{ $isForm ? 'Not Signed' : 'Not Submitted' }}</strong>

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

                        <strong>{{ $isForm ? 'Draft Saved' : 'Conflict' }}</strong>

                        <br><br>

                        <span style="font-size:28px;color:red;">
                            {{ $isForm ? $stats['draft'] : $stats['conflict'] }}
                        </span>

                    </td>

                </tr>

            </table>

        </td>

    </tr>

</table>