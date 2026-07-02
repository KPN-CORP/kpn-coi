<h3>

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
Status
</th>

</tr>

</thead>

<tbody>

@foreach($rawDeclarations as $row)
    <tr>
        <td>{{ $row->user?->employee_id }}</td>
        <td>{{ $row->user?->fullname }}</td>
        <td>{{ $row->status->value }}</td>
    </tr>
@endforeach


</tbody>

</table>