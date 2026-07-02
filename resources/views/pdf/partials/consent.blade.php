<div class="mt-5">

    <table>

        <tr>

            <td width="40">

                Full Name

            </td>

            <td>

                : {{ $declaration->user->employee->fullname }}

            </td>

        </tr>
        <tr>

            <td width="70">

                Date

            </td>

            <td>

                : {{ $declaration->updated_at->format('d F Y') }}

            </td>

        </tr>

    </table>

</div>