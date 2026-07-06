<h3 style="margin-top:15px;">

Recent Submission

</h3>

<table
border="1"
cellpadding="6"
style="margin-top:10px;"
>

<thead>

<tr
style="background:#eeeeee;"
>

<th>
Employee ID
</th>

<th>
Employee
</th>

<th>
Period
</th>
<th>
Status
</th>

</tr>

</thead>

<tbody>

@foreach($rawDeclarations as $row)
    <tr>
        <td>{{ $row->user?->employee->fullname ?? '-' }}</td>
        <td>{{ $row->user?->employee_id ?? '-' }}</td>
        <td>{{ $row->period ?? '-' }}</td>
        <td>{{ ucfirst($row->status?->value ?? '-') }}</td>
    </tr>
@endforeach


</tbody>

</table>